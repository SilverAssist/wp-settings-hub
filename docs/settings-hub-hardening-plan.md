# Settings Hub Hardening Implementation Plan

Tracking issue: [#48](https://github.com/SilverAssist/wp-settings-hub/issues/48).
Execution state: [`settings-hub-hardening-plan.json`](./settings-hub-hardening-plan.json), the source of truth for what is done. This file explains **why** and **how**, and it is not updated to record progress.

Lifecycle: `multi-session`. This applies because the work is phased by request, spans several sessions, and lands as one pull request per phase.

## Problem Statement

`silverassist/wp-settings-hub` v1.2.2 works for its single consumer, but an audit on 2026-10-14 found these problems:

1. **The `capability` argument is documented but not implemented.**
   - `IMPLEMENTATION.md` calls it "CRITICAL: Required in v1.1.1+" and blames 403 errors on omitting it.
   - The code hardcodes `manage_options` in all three menu calls (`src/SettingsHub.php:295`, `:307`, `:322`) and never reads the argument.
   - `silver-assist-security` passes `'capability' => 'manage_options'` (`src/Admin/AdminPanel.php:262`), and the hub ignores it.
   - **Decision:** implement the feature, then document what exists.
2. **The integration suite gives false assurance.**
   - All 24 tests pass against a real WordPress test install, but only 1 of the 8 "integration" tests drives WordPress admin state.
   - Four are fake: they assert core behaviour, bypass the hub, or are vacuous.
   - Nothing covers roles, `$submenu` contents, hook suffixes, the enqueued stylesheet's `src`/`ver`, action buttons or late registration.
3. **CI and release checks have gaps.**
   - CI auto-fixes style before checking it (`phpcbf || true`).
   - It tests WordPress `latest` only, although we claim 6.5+.
   - It never lints `integration-guide.php`, which currently has a parse error.
   - The release workflow lets `PACKAGE_VERSION`, the CSS cache-busting version, drift. It is still `1.2.0` at v1.2.2.
4. **Documentation has drifted.** The same topics are explained in 3 to 5 files, so they contradict the code and each other. Examples: PHP 8.3 vs 8.2, "Settings > Silver Assist" vs a top-level menu, a Brain Monkey fallback that no longer exists, `onclick` vs `addEventListener`, and a `composer phpcs:fix` script that does not exist.

## Current Architecture

- **One class.** `SilverAssist\SettingsHub\SettingsHub` (`src/SettingsHub.php`, 612 lines) is a `final` singleton.
- **Registration.**
  - `register_plugin( $slug, $name, $callback, $args )` stores plugin data. Only the first non-empty `plugin_file` is kept, and it is never stored.
  - It hooks `register_menus` on `admin_menu` priority 5 and `enqueue_styles` on `admin_enqueue_scripts`.
- **Menus.**
  - `register_menus()` registers the top-level `silver-assist` menu (position 80, inline SVG icon) and a Dashboard submenu once, guarded by `$parent_registered`.
  - It then calls `register_submenus()` for every plugin on every call, so a second call adds duplicate rows.
  - Every menu call uses `manage_options`.
- **Rendering.**
  - `render_dashboard()` shows cards. Each card has a Configure link, the plugin's `actions` and the `silverassist_settings_hub_plugin_actions` hook.
  - `render_plugin_page()` wraps the plugin callback with an `<h1>`, a version badge and optional tabs.
  - A callback action prints the callback's output raw inside an inline `<script>` click listener. The callback runs at render time.
- **Assets.**
  - `enqueue_styles()` loads `assets/css/settings-hub.css` with the handle `silverassist-settings-hub` and version `PACKAGE_VERSION` on any hook suffix containing `silver-assist`.
  - `get_package_asset_url()` resolves the URL from `plugin_file`'s `vendor/silverassist/wp-settings-hub`, falling back to `__DIR__` mapped through `ABSPATH`. It returns `''` silently if neither works.
- **Tests.**
  - `tests/Unit` and `tests/Integration` both extend `WP_UnitTestCase` through `tests/TestCase.php`, so both need the WordPress test suite and MySQL.
  - The singleton is reset by reflection in `tearDown` (unit) or `setUp` (integration).
  - Admin-menu globals are never reset.
- **Tooling.**
  - PHPCS uses the shared `SilverAssistWP` ruleset.
  - PHPStan 1.12 runs at level 8 with broad `ignoreErrors`. Without them there are 106 errors: 13 in `src/` and 93 in `tests/`. Three of the patterns no longer match anything.
  - CI runs PHP 8.2, 8.3 and 8.4 against WordPress `latest`, plus `composer audit`. The release workflow checks only that CHANGELOG contains the tag.

### WordPress core behaviour the design relies on (verified against WP 7.1.3)

- `add_menu_page()` stores the item whatever the user's capability (`wp-admin/includes/plugin.php:1398-1459`; the capability only gates attaching the page callback at `:1407`).
- `add_submenu_page()` drops the row and marks it in `$_wp_submenu_nopriv` for users without the capability (`plugin.php:1504-1507`).
- `user_can_access_admin_page()` checks a submenu page against the **submenu's own** capability, never the parent's (`plugin.php:2234-2240`).
- The top-level item stays visible while it has an accessible submenu (`wp-admin/includes/menu.php:175-200`), and it links to the first accessible submenu (`wp-admin/menu-header.php:157-173`).
- Result: an editor can reach an `edit_posts` plugin page under a `manage_options` parent. A probe confirmed it:

  | User | `page=silver-assist` | admin-only plugin | `edit_posts` plugin |
  | --- | --- | --- | --- |
  | editor | 403 | 403 | allowed |
  | administrator | allowed | allowed | allowed |

- `options.php` is gated separately by `option_page_capability_{$option_page}` (`wp-admin/options.php:29`, `:47`). Plugins that lower their capability and save through the Settings API must filter it.

## Proposed Changes

### Overview

Seven phases, each one branch and one PR, ordered so that every behaviour change lands on top of tests that pin the current behaviour:

1. CI and tooling
2. Test infrastructure and real integration tests
3. Per-plugin capability (releases as 1.3.0)
4. Code hardening
5. Documentation realignment
6. PHPStan tightening
7. Release and consumer verification

### Technical Approach

#### Capability design (Phase 3)

**API**

- `register_plugin()` accepts an optional `capability` in `$args`. The default is `manage_options`, which is today's behaviour.

**Validation**

- A private `normalize_capability( mixed $capability, string $slug ): string` returns `manage_options` for an absent or `null` value.
- It trims strings and accepts `^[A-Za-z0-9_-]+$`. It does not use `sanitize_key()`, because that lowercases and would break mixed-case custom capabilities.
- Anything else triggers `_doing_it_wrong()` and **fails closed** to `manage_options`.
- Meta capabilities that need an object (`edit_post`) are not supported, and the docs say so.

**Storage**

- The `array_merge` order stays as it is today, for backwards compatibility.
- `$plugin['capability']` is assigned **after** the merge, so it is always present and normalised.

**Filters**

The filters are resolved at `admin_menu` time, so filters added late still apply. Both names satisfy the `PrefixAllGlobals` rule.

- `silverassist_settings_hub_plugin_capability( $capability, $slug, $plugin )` is per plugin.
- `silverassist_settings_hub_dashboard_capability( 'manage_options' )` covers the parent menu and the Dashboard.

**Menus**

- `register_submenus()` uses `get_plugin_capability( $plugin )`.
- `register_parent_menu()` uses `get_dashboard_capability()`.
- The parent is **not** lowered to "the lowest plugin capability". Capabilities are not ordered, and core already routes the top-level click to the first accessible submenu.

**Visibility**

- A `get_visible_plugins()` helper uses `current_user_can()` to filter dashboard cards and tabs.
- The Dashboard tab is shown only to users with the dashboard capability. Without this it is a dead link for editors.
- When nothing is visible, the dashboard shows the existing empty state.

**Defence in depth**

- `render_plugin_page()` calls `wp_die( ..., 403 )` when the current user lacks the plugin's capability.

**Types**

- Add `capability: string` to the stored array shape and to the `get_plugins()` return shape. Define the shape once as a PHPStan type alias in Phase 6, and update the inline shapes in this phase.

#### Code hardening (Phase 4)

**Idempotent and late registration**

- Track registered submenu slugs so a second `admin_menu` pass adds nothing.
- If `register_plugin()` is called after `register_menus()` has run in this request (`did_action( 'admin_menu' )` and the hub's pass is done), register that plugin's submenu immediately, which works while `admin_menu` is still firing.
- Outside `admin_menu`, call `_doing_it_wrong()`.
- Document the supported registration window in one place.

**Exact hook-suffix match**

- Load the stylesheet only on `toplevel_page_silver-assist` or on suffixes starting with the parent's submenu prefix.
- Derive that prefix the way core does (`get_plugin_page_hookname()` or `$admin_page_hooks['silver-assist']`) instead of the literal `silver-assist_page_`. Core builds it from `sanitize_title()` of the **translated** parent title.

**Singleton**

- `__wakeup()` throws `\LogicException`.

**Action-button contract**

Keep backwards compatibility and make the contract explicit:

- the callback receives `$plugin_slug`;
- it is called at render time;
- its echoed output becomes the body of `function ( event ) { ... }`, which passes `event` and so fixes the reliance on `window.event` in `integration-guide.php`;
- `url` wins over `callback`;
- an action with neither is skipped;
- a numeric suffix keeps button ids unique;
- the misleading "AJAX" comment goes.

Moving to `wp_add_inline_script()` is out of scope.

**Version sync**

- Phase 4 sets `PACKAGE_VERSION` to `1.2.2`, the newest released CHANGELOG heading, so the cache-busting version stops being stale.
- It adds `test_package_version_matches_changelog`, which asserts the constant equals the first `## [x.y.z]` heading of `CHANGELOG.md` (`## [Unreleased]` is skipped). The test is red on `1.2.0` and green on `1.2.2`.
- Phase 7 moves the `[Unreleased]` entries to `## [1.3.0]` and bumps the constant to `1.3.0` in the same commit, so the test, the release workflow check (Phase 1) and the tag agree.

**CSS**

- Remove or fix the `@container` rule at `assets/css/settings-hub.css:299-303`, which queries the container element itself and can never match.

#### Tests (Phase 2, extended in Phases 3 and 4)

**Base test case**

- `tests/TestCase.php` resets the singleton in both `setUp` and `tearDown`.
- It resets `$menu`, `$submenu`, `$_wp_submenu_nopriv`, `$_wp_menu_nopriv`, `$_wp_real_parent_file`, `$_registered_pages`, `$_parent_pages` and `$admin_page_hooks`.
- It replaces `wp_styles()` with a fresh `WP_Styles`.
- It provides helpers: `login_as( $role )`, `fire_admin_menu()`, `render( callable )` and `make_vendor_fixture()`. The fixture uses `addCleanup` / `tearDown` for its temporary plugin tree under `WP_PLUGIN_DIR`.

**Unit suite**

- The unit suite logs in an administrator in `setUp`, so render tests keep passing once Phase 3 filters cards per user.
- The tests in `tests/Unit` keep their name but are documented as "WordPress-loaded, no admin flow". Introducing a mock-only tier is out of scope.

**Integration tests to remove**

Remove these four, and replace the weak assertions of `test_wordpress_admin_menu_integration`:

- `test_capabilities_check_for_settings_access`
- `test_admin_menu_hook_registration`
- `test_plugin_callback_execution`
- `test_tabs_navigation_with_multiple_plugins`

**New integration tests (Phase 2: characterise current behaviour)**

- `test_menu_registers_parent_dashboard_and_each_plugin_in_submenu`: checks the exact `$submenu['silver-assist']` rows, the capability, position 80 and the icon.
- `test_submenu_uses_tab_title_over_name`
- `test_rendering_plugin_page_through_registered_submenu_callback`: goes through the hook WordPress registered, not the stored closure.
- `test_active_tab_is_marked_on_plugin_page_and_dashboard`, plus the `enable_tabs( false )` case.
- `test_enqueue_styles_registers_handle_with_expected_src_and_version`
- `test_enqueue_runs_through_admin_enqueue_scripts_hook`: fires `do_action( 'admin_enqueue_scripts', 'toplevel_page_silver-assist' )`.
- `test_package_asset_url_primary_branch_requires_vendor_dir_and_file`
- `test_package_asset_url_fallback_branch`
- `test_hub_does_not_register_network_admin_menu`, in the multisite group. It documents the supported scope: per-site admin only.

**New unit tests (Phase 2: they call `render_dashboard()` or `get_plugins()` directly, so they belong in `tests/Unit`)**

- The action-button set: URL link escaped with its class, callback button id and `data-plugin`, empty label skipped, neither url nor callback skipped, and the `silverassist_settings_hub_plugin_actions` hook firing with `( $slug, $plugin )`.
- `test_register_plugin_overwrites_same_slug_and_keeps_position`
- `test_plugin_file_is_captured_only_from_first_registering_plugin`
- `test_dashboard_escapes_untrusted_name_description_version_and_slug`

**Tests that need fixes from later phases**

These are added in Phase 4 alongside the fix, because they fail today:

- `test_menu_registration_is_idempotent_when_admin_menu_fires_twice`
- `test_late_registration_after_priority_5_still_creates_menu`
- `test_enqueue_styles_on_exact_hub_hook_suffixes_only`
- `test_unserialize_is_rejected_and_clone_is_private`
- `test_package_version_matches_changelog`

#### CI and tooling (Phase 1)

**Style and linting**

- `quality-checks.yml`: delete the `composer phpcbf || true` step.
- Fix the `integration-guide.php` nested-comment parse error here (a comment-only change), and add a `php -l integration-guide.php` step so it cannot regress. Phase 5 reworks the example's content.

**Database and test runs**

- Replace `apt-get` and `systemctl start mysql` with a `mysql:8.0` service container that has a health check.
- Install with host `127.0.0.1`.
- Run the `unit` and `integration` suites as separate steps.

**Matrix**

- WordPress `6.5` × PHP `8.2`, and WordPress `latest` × PHP `8.2/8.3/8.4`. Keep the existing PHP matrix in `ci.yml`.
- Add one `WP_MULTISITE=1` job on `latest`.

**Action pins**

- Correct every `actions/checkout@3d3c42e…` comment to `# v7.0.1`.
- Pin `actions/github-script` to `3a2844b7e9c422d3c10d287c895573f7108da1b3 # v9`.

**Release workflow**

- `release.yml` validation fails when `PACKAGE_VERSION` differs from the tag.
- Make the `workflow_dispatch` path use `inputs.tag` for `VERSION`. Today it derives `refs/heads/main`.

**Coverage**

- Coverage is collected in exactly one job (PHP 8.2, WP latest, not multisite). The separate unit and integration steps run with `--no-coverage` in every job. The coverage job runs one extra step, `vendor/bin/phpunit --testsuite all`, and is the only job that uploads to Codecov.
- Codecov stays non-blocking until the gate exists. The gate is set in Phase 6, once the Phase 4 tests have landed: record the percentage then, and require that value minus 2 points through a `codecov.yml` project status check.
- Add `coverage.xml` to `.gitignore`, and confirm with `phpunit --list-tests` that the `unit`, `integration` and `all` suites never run a test twice (checked: 24 tests, no duplicates).

**Local setup**

- Replace `scripts/install-wp-tests.sh` with the 3-line wrapper around `vendor/silverassist/wp-coding-standards/scripts/install-wp-tests.sh`, as `silver-assist-security` does. That brings empty-password support and drops the `wp-tests-config.php` working-directory bug.
- Drop the hardcoded `<env name="WP_TESTS_DIR">` from `phpunit.xml`, so the bootstrap's `sys_get_temp_dir()` fallback is the single default.
- Load the polyfills from the bootstrap with an absolute path.
- Make the bootstrap's install hint `wp_settings_hub_test root '' 127.0.0.1 latest`.

#### Documentation (Phase 5)

One canonical home per topic. Other files link to it instead of restating it.

| Topic | Canonical file |
| --- | --- |
| Requirements (PHP 8.2+, WP 6.5+) | `composer.json` + README "Requirements" |
| Install, private `vcs` repository and token | README "Installing via Composer" |
| `register_plugin()` arguments, hooks and filters | PHPDoc in `src/SettingsHub.php` + README "API Reference" |
| Menu structure and hook suffixes | README "Menu Structure" |
| Registration timing, standalone fallback, update-button pattern | `IMPLEMENTATION.md` |
| Local setup, tests, quality checks, release checklist, commit convention, git hook | `CONTRIBUTING.md` |
| Version history | `CHANGELOG.md` only |
| AI-agent guidance | `.github/copilot-instructions.md`, a short pointer file |

Corrections, besides documenting the Phase 3 and 4 behaviour:

**`integration-guide.php`**

- The parse error is fixed in Phase 1. Here, add `plugin_file` to the example.
- Use the `event` parameter.

**`IMPLEMENTATION.md`**

- Change PHP 8.3 to 8.2.
- Verify the "Error 2: 403" section that Phase 3 rewrites (the user lacks the capability, the Dashboard needs the dashboard capability, `option_page_capability_{group}` is missing) against the code. Phase 4 changes the "registration came too late" cause.
- Rewrite "Error 4" to describe the `addEventListener` mechanism.
- Remove "built-in update support", the embedded changelog and the version footer.
- Trim the generic WordPress advice.
- Fix the hook-priority guidance.
- Add a "Registration timing" section with the supported window: call `register_plugin()` on any hook that fires before the end of `admin_menu` (`plugins_loaded`, `init`, or `admin_menu` at any priority). Outside `admin_menu` the hub calls `_doing_it_wrong()` and adds no submenu. This is the only place that states the window.
- Link to the README for hook suffixes. The README owns the suffix table (`toplevel_page_silver-assist`, `silver-assist_page_{slug}`, and the `settings_page_{slug}` standalone fallback) and recommends `get_plugin_page_hookname()` over string literals. This file owns only the timing rule.
- Add `plugin_file` to every `register_plugin()` example, use `^1.2`, and drop the Packagist link.
- Reduce the update-button material to one example plus its explanation, and make the JavaScript action names consistent between examples. The README keeps a 5-line summary.

**README**

- Replace both Development sections (`README.md:450-554`, including the stray fence at `:512` and the test counts) with a 3-line pointer to `CONTRIBUTING.md`. Change "PHPUnit 10" (`README.md:19`) to "PHPUnit 9.6", and delete "Comprehensive test suite" and "100% test coverage for new features" (`README.md:561`).
- Document the action contract: the callback receives `$plugin_slug`, is called at render time, and its echoed output becomes the body of `function ( event ) { ... }`; `url` wins over `callback`; an action with an empty label, or with neither `url` nor `callback`, is skipped; actions render only on dashboard cards.
- State that `tab_title` labels both the sidebar item and the tab, that `name` is the page title, `<h1>` and card heading, that `version` shows on the card and in the page header badge, and that re-registering a slug replaces its data.
- Add under Requirements and Menu Structure: "The hub registers its menu on `admin_menu` only. It supports single-site installs and the per-site admin of a multisite network, and registers nothing in the network admin or user admin."
- Replace the shield emoji at `README.md:94` with "inline SVG icon".
- Make every link to `IMPLEMENTATION.md`, `CONTRIBUTING.md` and `integration-guide.php` an absolute `https://github.com/SilverAssist/wp-settings-hub/blob/main/...` URL, because the release zip ships only `src/`, `assets/`, `vendor/`, `composer.json`, `LICENSE.md`, `README.md` and `CHANGELOG.md`.
- Remove the undocumented helper classes (`silverassist-plugin-settings`, `silverassist-settings-page`, ...) from the README and `integration-guide.php` examples, or add their styles.
- Document that `tab_title` also labels the sidebar item, that tabs default to on, and the `plugin_file` resolution rules (first wins, silent failure outside `ABSPATH`).
- Add a consolidated "Hooks and filters" table.
- Fix the broken `IMPLEMENTATION.md` anchor.
- Use `??` for the optional version in the example.
- Reconcile the Packagist badges with the private install story.

**`CONTRIBUTING.md`**

- Remove Brain Monkey and the "optional test suite" wording.
- State the prerequisites: svn, a running MySQL and the token.
- Document the `.githooks/commit-msg` hook and the `SilverAssistWP` ruleset.
- State the `TICKET: subject` commit convention and mention the audit CI job.
- Update the layout tree and the test-location paragraphs (`tests/Unit`, `tests/Integration`, `assets/`, `scripts/`, `.githooks/`). Say that the unit suite still boots WordPress and MySQL (no mock-only tier).
- Explain that the `skip-database-creation` argument (`true`) is only valid when the database already exists, as in CI with the service container.
- Document once that `composer test` runs without coverage and `composer phpunit` writes coverage reports.
- Use one spelling, "Silver Assist", and explain `security@` versus `support@`.

**Wording**

- Change "Settings > Silver Assist" to "top-level Silver Assist menu" in `composer.json`, the class docblock and the copilot instructions.

**Other**

- Text domain: either load `silverassist-settings-hub` and ship a `.pot` file, or state that hub strings are not translatable yet. The default is to state it, because loading needs a languages path inside `vendor/`.
- Remove the `slevomat/coding-standard` dev dependency, or wire it into the ruleset. The default is to remove it, because the shared ruleset owns standards.
- Delete `.packagist.yml`, or state in `CONTRIBUTING.md` why it remains, since the package is documented as installed through a `vcs` repository.
- Update the `src/SettingsHub.php` class docblock wording and the PHPDoc at `:179-180` ("before plugins register their submenus"; the hub registers the submenus itself).
- Leave historical CHANGELOG entries untouched.

#### PHPStan (Phase 6)

- Set `reportUnmatchedIgnoredErrors: true` and delete the 3 stale test patterns.
- Introduce `@phpstan-type PluginData array{...}` with `@phpstan-import-type` / `@phpstan-param` on the private renderers. Narrow the `$args` reads.
- Delete the broad `Right side of && is always true`, `Cannot cast`, `does not accept`, `expects string`, `expects callable` and `esc_html` ignores until `src/` passes with only the `ABSPATH` and WordPress-test-suite ignores.
- Upgrade to `phpstan/phpstan ^2.1` and `szepeviktor/phpstan-wordpress ^2.0` in a separate commit. If the upgrade surfaces too much in `tests/`, generate a baseline for `tests/` only and log it as a follow-up.

#### Release (Phase 7)

- Move the `[Unreleased]` entries to `## [1.3.0]` and add the compare links. The `[1.2.2]` link is missing today and `[Unreleased]` still compares from `v1.2.1`.
- Bump `PACKAGE_VERSION` to `1.3.0` in the same commit, then tag `v1.3.0`. Release CI validates the tag, CHANGELOG and constant together.
- **Before tagging**, verify the consumer: in `silver-assist-security`, require the merged commit with `composer require silverassist/wp-settings-hub:dev-main as 1.3.0` (or a path repository) and run its full test suite. Confirm that its `'capability' => 'manage_options'` registration renders identically. Tags are immutable, so this is the safety net.
- **After the release**, run `composer update silverassist/wp-settings-hub` in the consumer and confirm the lock resolves `1.3.0`.

### API Changes

- **New:** the optional `capability` key in `register_plugin()` `$args`. It defaults to `manage_options` and is always present in `get_plugins()` entries and in the `silverassist_settings_hub_plugin_actions` `$plugin` payload.
- **New filters:** `silverassist_settings_hub_plugin_capability` and `silverassist_settings_hub_dashboard_capability`.
- **Behaviour change:** `render_dashboard()` and the tabs show only plugins the current user can access. Called directly with no logged-in user, the dashboard renders the empty state. Inside wp-admin this cannot be observed, because the Dashboard already requires `manage_options`.
- **Behaviour change:** `unserialize()` of the hub throws.
- **Behaviour change:** callback actions receive the click `event` as `event` inside the generated listener. This is additive, because echoed code that ignores it is unaffected.
- **Behaviour change:** the stylesheet no longer loads on unrelated screens whose hook suffix merely contains `silver-assist`.
- **Fix:** registering after the hub's `admin_menu` pass now produces a submenu instead of silently producing nothing. Repeated `admin_menu` passes no longer duplicate rows.
- **Version:** 1.3.0, a minor release because everything is additive. Consumers on `^1.2` pick it up automatically.

## Risk Assessment

| Risk | Probability | Impact | Mitigation |
| --- | --- | --- | --- |
| Per-user card/tab filtering hides plugins from an admin who lacks a custom capability | Low | Medium | Intended behaviour. Documented. An integration test covers admin + custom capability. |
| Existing unit render tests fail once cards are filtered per user (they run as user 0) | High (certain) | Low | Phase 2 base `TestCase` logs in an administrator, before Phase 3 lands. |
| Exact hook-suffix matching misses a translated parent title | Medium | Medium | Derive the prefix from core (`$admin_page_hooks` / `get_plugin_page_hookname()`), not a literal. Test with a filtered `gettext` title. |
| Late-registration support registers a submenu in an unexpected context (outside `admin_menu`) | Low | Low | Register immediately only while `admin_menu` is running. Otherwise call `_doing_it_wrong()`. |
| Removing `phpcbf \|\| true` turns CI red on existing fixable violations | Low (PHPCS passes today) | Low | Run `composer phpcs` locally before pushing Phase 1. |
| WordPress 6.5 matrix job fails on APIs used by tests (not `src`) | Medium | Low | Fix the tests, or skip specific cases with a version guard. `src/` uses only long-standing APIs. |
| PHPStan 2.x upgrade surfaces many test-file errors | High | Low | Limit Phase 6 to `src/` cleanliness. Baseline `tests/` and log a follow-up. |
| The consumer (`silver-assist-security`) relies on undocumented behaviour (e.g. CSS loading on `settings_page_silver-assist-security`) | Medium | Medium | Phase 7 runs its full suite, including e2e if available, against the merged `main` (as `dev-main`) before the tag is created. The consumer enqueues its own assets for its standalone page. |
| Doc consolidation deletes content someone links to | Low | Low | Keep headings that are link targets, leaving a one-line pointer where content moved. |

## Phase Breakdown

Branch naming: `48-phase-<n>-<slug>`, created from an up-to-date `main` with `gh issue develop 48 --base main --name <branch>`. Exception: Phase 1 runs on the issue branch `48-settings-hub-hardening` that carries this plan. Every PR references #48 and updates `docs/settings-hub-hardening-plan.json` in the same PR. Run `core-review --budget medium` before each push, as the global instructions require.

### Phase 1: CI and tooling

**Objective:** CI fails on what it claims to check, tests the WordPress range we support, and the release cannot ship a stale cache-busting version.

- [ ] Remove `composer phpcbf || true` from `quality-checks.yml`.
- [ ] Fix the `integration-guide.php` nested-comment parse error (comment-only change) and add a `php -l integration-guide.php` step.
- [ ] Use a `mysql:8.0` service container with a health check and host `127.0.0.1`, and install the suite with `root root 127.0.0.1`.
- [ ] Add a WordPress version input to the reusable workflow. Matrix: WP 6.5 on PHP 8.2, WP latest on 8.2, 8.3 and 8.4. Add one `WP_MULTISITE=1` job.
- [ ] Run the `unit` and `integration` suites as separate steps.
- [ ] Correct the `actions/checkout` pin comments to `# v7.0.1`, and SHA-pin `actions/github-script` (`3a2844b7… # v9`).
- [ ] `release.yml`: validate `PACKAGE_VERSION` against the tag, and fix the `workflow_dispatch` `VERSION` derivation.
- [ ] Replace `scripts/install-wp-tests.sh` with the shared-script wrapper. Drop the hardcoded `WP_TESTS_DIR` env and the relative polyfills const from `phpunit.xml`. Fix the bootstrap install hint.
- [ ] `scripts/run-quality-checks.sh`: remove the `phpcbf` step from `all` and drop `--warning-severity=0`, so a clean local run matches `composer phpcs` and CI.
- [ ] Add `coverage.xml` to `.gitignore`, and confirm the `unit`, `integration` and `all` suites never run a test twice.

**Validation:** CI is green on the PR with the new matrix. A deliberately introduced PHPCS violation (local experiment, not committed) fails `composer phpcs`. `bash scripts/install-wp-tests.sh wp_settings_hub_test root '' 127.0.0.1 latest` followed by `vendor/bin/phpunit` works on macOS with no extra env vars.

### Phase 2: Test infrastructure and real integration tests

**Objective:** the integration suite proves what the hub does today, through real WordPress, before any behaviour changes.

- [ ] Shared `TestCase`: singleton and admin-menu-global reset, a fresh `WP_Styles`, the helpers `login_as()`, `fire_admin_menu()`, `render()` and `make_vendor_fixture()` with guaranteed cleanup.
- [ ] Unit `setUp` logs in an administrator.
- [ ] Delete the 4 fake integration tests and strengthen `test_wordpress_admin_menu_integration`.
- [ ] Add the characterisation tests listed under "Tests (Phase 2 …)" above.
- [ ] Apply the integration rule: an integration test must read `$menu`, `$submenu`, `wp_styles()`, `user_can_access_admin_page()` or the output of the callback WordPress registered (`get_plugin_page_hook()` / `$wp_filter`). Tests that call `render_dashboard()` or `get_plugins()` directly live in `tests/Unit`.
- [ ] Delete the vacuous `test_enqueue_styles_not_on_other_pages` (it passes with the filter removed, because URL resolution returns `''` without `plugin_file`). The Phase 4 exact-suffix test replaces it. Extend `test_enqueue_styles_on_silver_assist_page` to assert `src` and `ver`.
- [ ] Optional: port `scripts/run-phpunit-complete.sh` from `silver-assist-security`, which catches a test that calls `exit`.
- [ ] Informational: note the Codecov percentage in the plan JSON log. The coverage gate itself is set in Phase 6, after the Phase 4 tests have landed.

**Validation:** all suites green locally and in CI, including WP 6.5 and multisite. Every integration test fails if `register_menus`, `enqueue_styles` or the render path is stubbed out. Spot-check by temporarily breaking each one.

### Phase 3: Per-plugin capability

**Objective:** `register_plugin()` honours `capability` as designed, and the docs describe exactly that.

- [ ] Add the `DEFAULT_CAPABILITY` constant, and the helpers `normalize_capability()`, `get_plugin_capability()`, `get_dashboard_capability()` and `get_visible_plugins()`.
- [ ] Use them in `register_parent_menu()`, `register_submenus()`, `render_dashboard()` and `render_tabs_navigation()`. Add the `render_plugin_page()` guard.
- [ ] Update the PHPDoc `@type` list and all array shapes.
- [ ] Unit tests: default, custom, trim, invalid values (data provider plus `setExpectedIncorrectUsage`), mixed case, both filters, `capability` stored and `plugin_file` not, cards and tabs per role, empty state.
- [ ] Integration tests with real users:
  - editor reaches the `edit_posts` page;
  - editor gets 403 on the Dashboard and on admin-only pages;
  - admin sees everything;
  - subscriber sees nothing;
  - the top-level item stays for the editor;
  - the callback renders for the editor;
  - the guard calls `wp_die`;
  - regression: the `silver-assist-security`-style call renders identically.
- [ ] Docs: README API reference (argument and filters), the `IMPLEMENTATION.md` capability sections and "Error 2" rewrite, the `option_page_capability_{group}` caveat, `integration-guide.php` example, and a CHANGELOG `[Unreleased]` entry. The entry says: Added the optional `capability` argument and the filters `silverassist_settings_hub_plugin_capability` and `silverassist_settings_hub_dashboard_capability`. Changed: `get_plugins()` entries and the `silverassist_settings_hub_plugin_actions` `$plugin` payload now always contain `capability`; `render_dashboard()` and the tabs show only plugins the current user can access, so calling `render_dashboard()` with no logged-in user renders the empty state.

**Validation:** suite green. Manual check in a local WordPress install: log in as an editor and confirm that the "Silver Assist" menu shows only the `edit_posts` plugin, that `admin.php?page=silver-assist` returns 403, and that the plugin page renders without a Dashboard tab.

### Phase 4: Code hardening

**Objective:** close the behavioural gaps the audit found in `src/` and the CSS.

- [ ] Idempotent `register_submenus()` and late registration, with `_doing_it_wrong()` outside `admin_menu`.
- [ ] Exact hook-suffix match derived from core, plus a test with a translated parent title.
- [ ] `__wakeup()` throws `\LogicException`.
- [ ] Action-button contract: pass `event`, give unique ids, fix the comment, add tests.
- [ ] Set `PACKAGE_VERSION` to `1.2.2` and add the CHANGELOG-sync unit test (see "Version sync").
- [ ] State the supported registration window in the `register_plugin()` PHPDoc. The long-form text lands in Phase 5 (`IMPLEMENTATION.md`, "Registration timing").
- [ ] Fix or remove the dead `@container` rule.
- [ ] Add the five "need fixes from later phases" tests listed above.
- [ ] Add a CHANGELOG `[Unreleased]` entry.

**Validation:** suite green. The five new tests were red before the fix and are green after it. Record the red run in the JSON log.

### Phase 5: Documentation realignment

> **Split:** the behaviour-independent part was done early as Phase 5a (its own PR, written in parallel with Phase 3): the README Development pointer, PHPUnit 9.6, absolute links, the `tab_title`/`name`/`version` semantics, `plugin_file` resolution, the multisite sentence, CONTRIBUTING, the copilot pointer file, the generic trimming of IMPLEMENTATION.md and the `integration-guide.php` examples. What is left below is the documentation of Phase 4 behaviour and the open decisions (install story, `composer.json` wording, slevomat, text domain, `.packagist.yml`).

**Objective:** no documentation claim contradicts the code, and each topic has one canonical home.

- [ ] Apply the canonical-home table and every correction listed under "Documentation (Phase 5)".
- [ ] Rewrite `.github/copilot-instructions.md` as a short pointer file.
- [ ] Remove or wire in `slevomat/coding-standard`, and decide the text-domain wording.
- [ ] Run `core-review --budget thorough` and re-run until it reports nothing new.

**Validation:** `core-review` is clean. Spot-check every code-like snippet in the docs against `src/SettingsHub.php`. All internal links and anchors resolve, and every link to a file missing from the release zip is an absolute GitHub URL.

### Phase 6: PHPStan tightening

**Objective:** static analysis passes for the right reasons.

- [ ] Set `reportUnmatchedIgnoredErrors: true` and remove the stale patterns.
- [ ] Add a `PluginData` PHPStan type alias, narrow the `$args` reads, and delete the broad ignores so `src/` passes cleanly.
- [ ] Upgrade to PHPStan 2.x in a separate commit, baselining `tests/` only if needed and logging it as a follow-up.
- [ ] Set the coverage gate: record the Codecov percentage after Phase 4 has merged, then add a `codecov.yml` project status check at that value minus 2 points.

**Validation:** `composer phpstan` passes. `src/` passes with only the `ABSPATH` / test-suite ignores.

### Phase 7: Release 1.3.0 and consumer verification

**Objective:** ship, and prove the only consumer is unaffected.

- [ ] CHANGELOG `[1.3.0]` entry and compare links (moving the `[Unreleased]` entries), and `PACKAGE_VERSION = '1.3.0'` in the same commit.
- [ ] After the Phase 7 PR is merged and **before tagging**, run the `silver-assist-security` suite (unit, integration, plus e2e if available) against the merged commit as `dev-main as 1.3.0`. Record the result in the JSON.
- [ ] Only if green, tag `v1.3.0` and confirm the release workflow.
- [ ] After the release, run `composer update silverassist/wp-settings-hub` in the consumer and confirm the lock resolves `1.3.0`.
- [ ] Close #48.

**Validation:** the consumer suite was green before the tag, the GitHub release exists with the zip, and the consumer lock resolves 1.3.0.

## Testing Strategy

### Unit Tests

- Capability normalisation: default, custom value, trim, invalid values, mixed case and both filters.
- `get_plugins()` contents: `capability` stored, `plugin_file` stripped, first `plugin_file` wins.
- Rendering per role: cards, tabs, Dashboard tab and empty state.
- Singleton: `__wakeup()` throws and `__clone()` is private.
- `PACKAGE_VERSION` matches CHANGELOG.

### Integration Tests

- The menu tree through `do_action( 'admin_menu' )`: parent, Dashboard, plugin rows, capability, `tab_title`, order.
- Access per role through `user_can_access_admin_page()`, with real administrator, editor and subscriber users.
- Page rendering through the hook WordPress registered.
- Styles: exact hook suffixes, then `src` and `ver` of the registered handle through `admin_enqueue_scripts`.
- Asset URL resolution: primary branch, fallback branch, unresolvable.
- Idempotent and late registration.
- Action buttons and the `silverassist_settings_hub_plugin_actions` hook.
- Escaping of untrusted plugin metadata.
- Multisite: per-site menu works, and no network-admin menu is registered.

### Manual Testing

1. Install the branch build in a local WordPress site that also runs `silver-assist-security`.
2. As administrator, the Dashboard, the cards, the tabs, the Silver Assist Security page and its Check Updates action all work, and the stylesheet loads only on hub screens.
3. Register a test plugin with `'capability' => 'edit_posts'`. As editor, only that plugin appears under Silver Assist, the Dashboard returns 403 and the page renders without a Dashboard tab.
4. On multisite, the hub works in a sub-site admin and is absent from network admin.

## Rollback Plan

- Every phase is an isolated PR. Revert the merge commit of the offending phase.
- Phase 3 is additive. If it misbehaves for consumers, revert it and tag `v1.3.1` from the reverted `main`. Consumers on `^1.2` fall back to the previous behaviour on their next `composer update`. Do not delete or move existing tags, because tags are immutable by policy.
- CI changes (Phase 1) can be reverted independently. They do not affect the shipped package.

## Out of scope (logged as follow-ups on #48)

- A hub helper for `option_page_capability_{group}`. The caveat is documented only.
- A separate capability for action buttons. Actions inherit the plugin capability, and cards are already hidden from users without it.
- Locking `name`, `slug` and `callback` against `$args` overrides (kept for backwards compatibility).
- An integration test that saves through the Settings API with a lowered capability.
- Logging, or a filter, when the stylesheet URL cannot be resolved. The silent failure is documented.
- Stricter `phpunit.xml` settings (`backupGlobals`, error conversion), a `plugins_loaded` fixture plugin, and a WordPress trunk job.

## Dependencies

- **External:**
  - The WordPress test suite (svn) and MySQL 8, locally and as a CI service container.
  - The GitHub token (`COMPOSER_AUTH`) for the private SilverAssist dev dependencies.
  - Codecov, which stays non-blocking until the threshold is set.
- **Internal:**
  - `silverassist/wp-coding-standards` ships the shared `install-wp-tests.sh` that Phase 1 wraps.
  - `silver-assist-security` is the only consumer and the Phase 7 verification target, and its `quality-checks.yml` is the reference CI setup.
- **Coordination:** none beyond review. The consumer needs no code change, because it already passes `'capability' => 'manage_options'`.
