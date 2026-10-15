# Settings Hub Design System

The visual rules for every screen the hub draws: the dashboard, the plugin page header and the cross-plugin tabs. The tokens and class names below are the contract. `assets/css/settings-hub.css` implements them, and the Playwright suite in `tests/e2e/` fails when the two disagree (see [Keeping the doc and the CSS in sync](#keeping-the-doc-and-the-css-in-sync)).

The direction is **border-first and monochrome**: hairline borders define containers, the brand navy marks the one primary action, the brand teal marks keyboard focus, a pale blue marks the active tab, and small pills carry metadata. It is adapted from the Dub dashboard style to fit inside wp-admin.

## Principles

1. **Borders, not shadows.** A 1px border defines every container. The only shadows are a barely-there lift on buttons and cards on hover.
2. **One primary action per surface.** One filled brand-navy button per card. Everything else is outlined.
3. **One accent.** The brand teal (`--silver-hub-teal`) is the keyboard focus ring, never a fill and never text.
4. **Metadata is neutral.** A version number is information, not status, so it is a grey pill. Colour is reserved for real status.
5. **Native to wp-admin.** The hub inherits the admin font and canvas, and it never loads a webfont or restyles anything outside its own classes.
6. **Accessible by default.** Every text pair meets WCAG AA (4.5:1), every interactive element has a visible keyboard focus ring, and motion respects `prefers-reduced-motion`.

## Adaptation to wp-admin

| Reference style | Used here | Reason |
| --- | --- | --- |
| Satoshi, Inter and Geist Mono | The wp-admin font; a system monospace stack for version pills only | No webfont requests, and the screen stays native. The display face is meant for 36px+ marketing headlines, which the hub does not have |
| White canvas | wp-admin's canvas (`#f0f0f1`) with white bordered cards | Overriding `#wpbody` would restyle every admin screen and every consumer page |
| Black fill and Deep Sapphire `#1e40af` for the primary action | `--silver-hub-navy` (`#003073`, the Silver Assist primary); neither black nor Deep Sapphire is used | The brand colour is the filled action |
| Electric Blue `#2563eb` as the accent | `--silver-hub-teal` (`#2d8ab3`, the Silver Assist secondary), for the focus ring only | The brand teal passes the 3:1 minimum for non-text (3.89:1 on white, 3.42:1 on the wp-admin canvas). The brand cyan `#00d1ff` does not (1.82:1), so it is not used for UI |
| Mint badge with green text | Neutral pill for versions; `#166534` text on `#dcfce7` for future status badges | `#16a34a` on `#dcfce7` is 3.00:1 and fails AA, `#166534` is 6.49:1 |
| Conic gradient, dotted grid, large display type | Not used | Brand visuals, not UI |

## Colour

### Primitives

| Token | Value | Role |
| --- | --- | --- |
| `--silver-hub-navy` | `#003073` | Brand primary. Primary action fill, active tab text |
| `--silver-hub-navy-deep` | `#001b40` | Brand dark. Primary action hover |
| `--silver-hub-charcoal` | `#171717` | Body text, primary action hover |
| `--silver-hub-slate` | `#404040` | Inactive tab text |
| `--silver-hub-steel` | `#525252` | Muted text, descriptions |
| `--silver-hub-fog` | `#737373` | Subtle icons. Text only on white (4.74:1), never on paper (4.35:1) |
| `--silver-hub-smoke` | `#d4d4d4` | Stronger borders, hover borders, dashed empty-state border |
| `--silver-hub-ash` | `#e5e5e5` | The default 1px border |
| `--silver-hub-paper` | `#f5f5f5` | Pills, icon tiles, hover fills |
| `--silver-hub-white` | `#fff` | Card surface, outlined button surface |
| `--silver-hub-teal` | `#2d8ab3` | Brand secondary. Focus ring only |
| `--silver-hub-blue-tint` | `#dbeafe` | Active tab background |
| `--silver-hub-green-tint` | `#dcfce7` | Status badge background (reserved) |
| `--silver-hub-green-text` | `#166534` | Status badge text (reserved) |

### Semantic roles

Build components from these, not from the primitives, so a theme change is one edit.

| Token | Resolves to | Role |
| --- | --- | --- |
| `--silver-hub-text` | `--silver-hub-charcoal` | Default text |
| `--silver-hub-text-muted` | `--silver-hub-steel` | Descriptions, secondary labels |
| `--silver-hub-text-subtle` | `--silver-hub-fog` | Decorative icons |
| `--silver-hub-surface` | `--silver-hub-white` | Cards, outlined buttons |
| `--silver-hub-surface-alt` | `--silver-hub-paper` | Pills, icon tiles, hover fills |
| `--silver-hub-border` | `--silver-hub-ash` | Default container border |
| `--silver-hub-border-strong` | `--silver-hub-smoke` | Hover and emphasis border |
| `--silver-hub-action` | `--silver-hub-navy` | Primary button fill |
| `--silver-hub-action-hover` | `--silver-hub-navy-deep` | Primary button hover |
| `--silver-hub-action-text` | `--silver-hub-white` | Primary button text |
| `--silver-hub-focus` | `--silver-hub-teal` | Focus ring |

### Contrast

These are the pairs the hub uses. Add a pair here, and to the Playwright contrast check, before using a new one.

| Text | Background | Ratio |
| --- | --- | --- |
| `--silver-hub-text` | `--silver-hub-surface` | 17.93:1 |
| `--silver-hub-text-muted` | `--silver-hub-surface` | 7.81:1 |
| `--silver-hub-text-muted` | `--silver-hub-surface-alt` | 7.17:1 |
| `--silver-hub-text-muted` | wp-admin canvas `#f0f0f1` | 6.86:1 |
| `--silver-hub-action-text` | `--silver-hub-action` | 12.57:1 |
| `--silver-hub-navy` | `--silver-hub-blue-tint` | 10.30:1 |
| `--silver-hub-action-text` | `--silver-hub-action-hover` | 17.06:1 |
| `--silver-hub-green-text` | `--silver-hub-green-tint` | 6.49:1 |
| `--silver-hub-teal` (focus ring, non-text) | `--silver-hub-surface` | 3.89:1 |
| `--silver-hub-teal` (focus ring, non-text) | wp-admin canvas `#f0f0f1` | 3.42:1 |

## Typography

The hub inherits the wp-admin font and sets size, weight and line height only. Version pills use `--silver-hub-font-mono`.

| Token | Value | Use |
| --- | --- | --- |
| `--silver-hub-font-mono` | `ui-monospace, "SF Mono", "Cascadia Mono", Menlo, Consolas, monospace` | Version pills |
| `--silver-hub-text-caption` | `11px` | Version pills |
| `--silver-hub-text-small` | `12px` | Count pill |
| `--silver-hub-text-body` | `14px` | Descriptions, buttons, tabs |
| `--silver-hub-text-lead` | `16px` | Card titles |
| `--silver-hub-text-title` | `20px` | Reserved for future section headings |
| `--silver-hub-weight-regular` | `400` | Body |
| `--silver-hub-weight-medium` | `500` | Buttons, tabs, page title, pills |
| `--silver-hub-weight-semibold` | `600` | Card titles, active tab |

Headings stay at weight 500 or 600. Never bold (700) and never uppercase.

## Spacing

A 4px base unit. Card padding is `--silver-hub-space-5`, gaps inside a card are `--silver-hub-space-2` and `--silver-hub-space-4`, and the grid gap is `--silver-hub-space-4`.

| Token | Value |
| --- | --- |
| `--silver-hub-space-1` | `4px` |
| `--silver-hub-space-2` | `8px` |
| `--silver-hub-space-3` | `12px` |
| `--silver-hub-space-4` | `16px` |
| `--silver-hub-space-5` | `20px` |
| `--silver-hub-space-6` | `24px` |
| `--silver-hub-space-8` | `32px` |
| `--silver-hub-space-12` | `48px` |

## Shape

Four radii and nothing else. A value outside this list is a bug.

| Token | Value | Use |
| --- | --- | --- |
| `--silver-hub-radius-input` | `6px` | Inputs |
| `--silver-hub-radius-button` | `8px` | Buttons, icon tiles |
| `--silver-hub-radius-card` | `12px` | Cards, empty state |
| `--silver-hub-radius-pill` | `9999px` | Tabs, version pill, count pill, badges |

## Elevation and motion

| Token | Value | Use |
| --- | --- | --- |
| `--silver-hub-shadow-subtle` | `0 1px 2px 0 rgba(0, 0, 0, 0.05)` | Outlined buttons, card hover |
| `--silver-hub-shadow-ring` | `0 0 0 4px rgba(0, 0, 0, 0.1)` | Reserved for a future floating panel |
| `--silver-hub-transition-fast` | `0.15s ease` | Border, background and colour changes |

Animate colour, border and background only. Do not move or scale elements on hover. Under `prefers-reduced-motion: reduce` every transition is removed.

## Layout

| Token | Value | Use |
| --- | --- | --- |
| `--silver-hub-max-width` | `1200px` | Dashboard grid and empty state |

The grid is `repeat(auto-fill, minmax(min(100%, 300px), 1fr))`. Below 782px (the wp-admin breakpoint) card actions stack full width at 40px height and the tabs scroll horizontally on one line.

## Components

| Component | Class | Notes |
| --- | --- | --- |
| Page title | `.wrap > h1` | Weight 500. Version pill sits inline |
| Page version pill | `.version-badge` | Neutral pill, monospace |
| Dashboard intro | `.silverassist-dashboard-description` | Max 65 characters wide |
| Plugin count | `.silverassist-dashboard-count` | Outlined pill after the intro text |
| Dashboard grid | `.silverassist-dashboard-grid` | Responsive, max 1200px |
| Plugin card | `.silverassist-plugin-card` | An `<article>` labelled by its title. 1px border, 12px radius, no shadow at rest |
| Card header | `.card-header` | Icon tile, title and version pill |
| Card body | `.card-content` | Description, then actions pinned to the bottom |
| Version pill | `.silverassist-version-badge` | Paper fill, border, monospace 11px |
| Card actions | `.plugin-actions` | Wraps. Stacks on mobile |
| Tabs | `.silverassist-hub-tabs` | Pill group on `.nav-tab-wrapper`. The active tab has `aria-current="page"` |
| Empty state | `.silverassist-empty-state` | Dashed border, icon tile, message and hint |
| Empty-state hint | `.silverassist-empty-state-hint` | Says what to do next |

### Buttons

One filled button per card: `Configure` (`.button-primary`) is brand navy with white text (`--silver-hub-navy-deep` on hover), 8px radius, 32px high. Every other action is `.button`: white fill, 1px border, subtle shadow. Plugin-supplied `class` values still work, because the hub styles `.button` and `.button-primary` inside the card.

### Tabs

Ghost pills: transparent at rest, paper fill on hover, `--silver-hub-blue-tint` fill with `--silver-hub-navy` text and weight 600 when active. The `<nav>` has an `aria-label`, and the active tab has `aria-current="page"`.

### Status badges (reserved)

The hub has none yet. When one is added: pill radius, `--silver-hub-green-tint` fill with `--silver-hub-green-text` text for success, 6px by 10px padding, and a coloured dot. Add the matching token pair and contrast row to this document in the same change.

## Accessibility

- Text meets 4.5:1 and the focus ring meets 3:1 against the surface it sits on.
- Every link and button inside the hub shows a 2px `--silver-hub-focus` outline with a 2px offset on `:focus-visible`.
- Card action buttons are 40px high below 782px.
- Landmarks: tabs are a `<nav aria-label>`, each card is an `<article aria-labelledby>`.
- Icons that only decorate use `aria-hidden="true"`.
- Nothing relies on colour alone: the active tab is also heavier, and has `aria-current`.

## Do and don't

**Do**

- Use the semantic tokens in components and the primitives only to define them.
- Use `--silver-hub-border` for container edges.
- Keep one filled action per card.
- Add new colours to this document, with a contrast row, before using them.

**Don't**

- Don't add drop shadows for depth, or move cards on hover.
- Don't use the teal as a fill, or on text. Don't use the brand cyan `#00d1ff` for text or focus (1.82:1).
- Don't use a radius outside the four above.
- Don't use `--silver-hub-fog` for text on paper.
- Don't load fonts or reset anything outside `.silverassist-*`, `.card-*`, `.plugin-actions`, `.silverassist-hub-tabs` and the page `h1`.

## For consumer plugins

Your settings page renders inside the hub's `.wrap`, under the hub header and tabs. Style your own content with the semantic tokens and it will match:

```css
.my-plugin-panel {
	background: var(--silver-hub-surface);
	border: 1px solid var(--silver-hub-border);
	border-radius: var(--silver-hub-radius-card);
	padding: var(--silver-hub-space-5);
}
```

The tokens are defined only when the hub stylesheet is enqueued, which happens on every Silver Assist admin page. The pre-1.3 names (`--silver-hub-color-*`, `--silver-hub-spacing-*`, `--silver-hub-font-size-*`, `--silver-hub-radius-md|lg`) still resolve as aliases. Note that `--silver-hub-color-primary` is now the brand navy `#003073` instead of `#0073aa`. Prefer the tokens in this document in new code.

## Changing the system

1. Edit the token in `assets/css/settings-hub.css` and in the table above in the same commit.
2. If a text colour or background changed, recompute the contrast rows and update the Playwright contrast list.
3. Run `npm run test:e2e`. It fails when a documented token or class is missing from the CSS, or has a different value.
4. Add a CHANGELOG entry. Changing a token a consumer may read is a `Changed` entry.

## Keeping the doc and the CSS in sync

`tests/e2e/design-system.spec.ts` reads the token tables in this file and checks each value against the computed style in a real wp-admin. It also checks the component classes, the radius vocabulary, borders and shadows, the focus ring, reduced motion, the 360px layout and accessibility (axe, including colour contrast). Run it with:

```bash
npm install
npx playwright install chromium
npm run test:e2e
```

The suite starts WordPress Playground (WordPress in WebAssembly, no Docker or MySQL), mounts this repository and a fixture plugin from `tests/e2e/fixtures/settings-hub-e2e/`, and logs in as `admin`.
