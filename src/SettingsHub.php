<?php
/**
 * Settings Hub for Silver Assist plugins.
 *
 * @package SilverAssist\SettingsHub
 * @since 1.0.0
 */

declare(strict_types=1);

namespace SilverAssist\SettingsHub;

/**
 * Centralized settings hub for all Silver Assist plugins.
 *
 * Provides a top-level "Silver Assist" admin menu (position 80) with a Dashboard
 * submenu, one submenu per registered plugin, a dashboard card for each of them,
 * and optional cross-plugin navigation tabs. A plugin registers itself with
 * register_plugin() and chooses the capability a user needs to see its page.
 *
 * @since 1.0.0
 */
final class SettingsHub {
	/**
	 * Singleton instance.
	 *
	 * @var SettingsHub|null
	 */
	private static ?SettingsHub $instance = null;

	/**
	 * Registered plugins.
	 *
	 * @var array<string, array{
	 *     name: string,
	 *     slug: string,
	 *     callback: callable,
	 *     capability: string,
	 *     description?: string,
	 *     version?: string,
	 *     tab_title?: string,
	 *     actions?: array<array{label: string, url?: string, callback?: callable, class?: string}>
	 * }>
	 */
	private array $plugins = array();

	/**
	 * Parent menu slug.
	 *
	 * @var string
	 */
	private const PARENT_SLUG = 'silver-assist';

	/**
	 * Capability required when a plugin does not declare one.
	 *
	 * @var string
	 */
	private const DEFAULT_CAPABILITY = 'manage_options';

	/**
	 * Whether the parent menu has been registered.
	 *
	 * @var bool
	 */
	private bool $parent_registered = false;

	/**
	 * Slugs whose submenu page has already been added, so a repeated pass never duplicates a row.
	 *
	 * @var array<string, true>
	 */
	private array $registered_submenus = array();

	/**
	 * Button ids already printed during the current dashboard render.
	 *
	 * @var array<string, int>
	 */
	private array $action_button_ids = array();

	/**
	 * Whether to render tabs for cross-plugin navigation.
	 *
	 * @var bool
	 */
	private bool $render_tabs = true;

	/**
	 * Whether the styles hook has been registered.
	 *
	 * @var bool
	 */
	private bool $styles_registered = false;

	/**
	 * Plugin file path used to resolve asset URLs.
	 *
	 * Stored from the first registration that passes a non-empty plugin_file;
	 * later values are ignored. Used to derive the vendor directory URL,
	 * avoiding the __DIR__ bug when PHP autoloader loads the class from a
	 * single vendor directory.
	 *
	 * @see https://github.com/SilverAssist/wp-github-updater/issues/17
	 *
	 * @var string
	 */
	private string $plugin_file = '';

	/**
	 * CSS handle for deduplication across plugins.
	 *
	 * @var string
	 */
	private const CSS_HANDLE = 'silverassist-settings-hub';

	/**
	 * Package version for cache busting.
	 *
	 * @var string
	 */
	private const PACKAGE_VERSION = '1.2.2';

	/**
	 * Private constructor to enforce singleton pattern.
	 */
	private function __construct() {
		// Singleton - use get_instance().
	}

	/**
	 * Get singleton instance.
	 *
	 * @return SettingsHub Singleton instance.
	 */
	public static function get_instance(): SettingsHub {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register a plugin with the settings hub.
	 *
	 * Automatically creates the top-level "Silver Assist" menu (and its Dashboard
	 * submenu) if it doesn't exist, and adds the plugin as a submenu item. The hub
	 * hooks `admin_menu` only, so nothing is added to the network or user admin.
	 *
	 * Call it before `admin_menu` fires or from an `admin_menu` callback, at any
	 * priority: a plugin registered while `admin_menu` is running, even after the
	 * hub's own priority 5 pass, gets its submenu immediately. A registration after
	 * `admin_menu` has finished is reported with `_doing_it_wrong()`, because core
	 * has already built the menu for the request.
	 *
	 * Registering a slug that is already registered replaces its stored data, which
	 * the dashboard card and the tabs use, and keeps its position. The submenu page
	 * is added only once, so after `admin_menu` has run it keeps the title, label,
	 * capability and callback of the first registration.
	 *
	 * @param string                                                      $slug     Unique plugin slug (e.g., 'post-revalidate').
	 * @param string                                                      $name     Display name for the plugin.
	 * @param callable                                                    $callback Function to render the plugin's settings page.
	 * @param array<string, string|array<array<string, string|callable>>> $args {
	 *     Optional arguments for the plugin.
	 *
	 *     @type string   $capability   Capability a user needs to see and open the plugin's page (since 1.3.0).
	 *                                  Defaults to 'manage_options'. An invalid value falls back to the default
	 *                                  and triggers `_doing_it_wrong()`.
	 *     @type string   $description  Plugin description shown on the dashboard card.
	 *     @type string   $version      Plugin version number shown as a badge on the card and the page.
	 *     @type string   $tab_title    Custom title for the tab and the sidebar submenu (defaults to plugin name).
	 *     @type string   $plugin_file  Absolute path to the plugin's main file (for asset URL resolution). The
	 *                                  first non-empty value from any registration is used and never stored in
	 *                                  the plugin data. If the vendor directory cannot be resolved from it and
	 *                                  the package is outside ABSPATH, the stylesheet is not enqueued.
	 *     @type array[]  $actions      Array of action buttons on the card. Each has a `label` and either a `url`
	 *                                  (rendered as a link) or a `callback` (rendered as a button), and an optional
	 *                                  `class`. The callback receives the plugin slug while the dashboard renders
	 *                                  and prints the JavaScript that becomes the click handler body, where `event`
	 *                                  is the click event.
	 * }
	 */
	public function register_plugin( string $slug, string $name, callable $callback, array $args = array() ): void {
		// Capture the first plugin_file for asset URL resolution.
		if ( empty( $this->plugin_file ) && ! empty( $args['plugin_file'] ) ) {
			$this->plugin_file = (string) $args['plugin_file'];
		}

		// Remove plugin_file from args to prevent it from being stored in plugin data.
		unset( $args['plugin_file'] );

		// Store plugin data.
		$plugin = array_merge(
			array(
				'name'     => $name,
				'slug'     => $slug,
				'callback' => $callback,
			),
			$args
		);

		// Set after the merge so the stored value is always present and normalised.
		$plugin['capability'] = $this->normalize_capability( $args['capability'] ?? null, $slug );

		$this->plugins[ $slug ] = $plugin;

		// Hook into admin_menu to register menus (WordPress ignores the repeat of an identical callback).
		add_action( 'admin_menu', array( $this, 'register_menus' ), 5 );

		// admin_menu already started: the priority 5 pass may have run without this plugin, so build its menu now.
		if ( did_action( 'admin_menu' ) > 0 ) {
			if ( ! doing_action( 'admin_menu' ) ) {
				_doing_it_wrong(
					__METHOD__,
					sprintf(
						/* translators: %s: plugin slug. */
						esc_html__( 'Plugin "%s" was registered after the admin_menu action finished. Register on or before admin_menu so its menu page exists in the request.', 'silverassist-settings-hub' ),
						esc_html( $slug )
					),
					'1.3.0'
				);
			}

			$this->register_menus();
		}

		// Register styles hook once (handle deduplication is done by WordPress).
		if ( ! $this->styles_registered ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
			$this->styles_registered = true;
		}
	}

	/**
	 * Enable or disable tab rendering.
	 *
	 * @param bool $enable Whether to render tabs.
	 */
	public function enable_tabs( bool $enable ): void {
		$this->render_tabs = $enable;
	}

	/**
	 * Register parent and submenu items.
	 *
	 * Hooked to admin_menu at priority 5 by register_plugin(). It adds the parent
	 * menu once and then adds the submenu page of every registered plugin itself,
	 * so plugins do not call add_submenu_page(). register_plugin() also calls it
	 * directly for a plugin that registers once admin_menu has started. Safe to
	 * call repeatedly: the parent and each plugin page are added once.
	 *
	 * @internal
	 */
	public function register_menus(): void {
		// Register parent menu once.
		if ( ! $this->parent_registered ) {
			$this->register_parent_menu();
			$this->parent_registered = true;
		}

		// Register submenus for all plugins.
		$this->register_submenus();
	}

	/**
	 * Enqueue dashboard styles on Silver Assist pages.
	 *
	 * Hooked to admin_enqueue_scripts once, by the first register_plugin() call.
	 * Loads the stylesheet only when the hook suffix belongs to the hub's menu
	 * (the Dashboard or a plugin page), and only when the asset URL can be resolved.
	 * Uses a unique handle so WordPress deduplicates the enqueue
	 * regardless of how many plugins include this package.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 *
	 * @internal
	 */
	public function enqueue_styles( string $hook_suffix ): void {
		if ( ! $this->is_hub_hook_suffix( $hook_suffix ) ) {
			return;
		}

		$css_url = $this->get_package_asset_url( 'assets/css/settings-hub.css' );

		if ( empty( $css_url ) ) {
			return;
		}

		wp_enqueue_style(
			self::CSS_HANDLE,
			$css_url,
			array(),
			self::PACKAGE_VERSION
		);
	}

	/**
	 * Whether a hook suffix belongs to a page of the hub's menu.
	 *
	 * Core names the Dashboard `toplevel_page_silver-assist`, and any other submenu page
	 * `{sanitize_title( translated menu title )}_page_{slug}`, storing that prefix in
	 * $admin_page_hooks. The match reads the prefix from there instead of assuming the
	 * English title. A plain substring check would also
	 * accept look-alikes such as settings_page_silver-assist-security.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 *
	 * @return bool
	 */
	private function is_hub_hook_suffix( string $hook_suffix ): bool {
		if ( 'toplevel_page_' . self::PARENT_SLUG === $hook_suffix ) {
			return true;
		}

		$prefix = $GLOBALS['admin_page_hooks'][ self::PARENT_SLUG ] ?? sanitize_title( __( 'Silver Assist', 'silverassist-settings-hub' ) );

		return is_string( $prefix ) && 0 === strpos( $hook_suffix, $prefix . '_page_' );
	}

	/**
	 * Get the URL to a package asset file.
	 *
	 * Resolves the URL to assets within the vendor/silverassist/wp-settings-hub directory.
	 * Uses plugin_file (provided via register_plugin) to derive the correct vendor path,
	 * replicating the pattern from wp-github-updater's getPackageAssetUrl().
	 *
	 * @param string $asset_path Relative path to asset (e.g., 'assets/css/settings-hub.css').
	 * @return string Full URL to the asset file, or empty string if unresolvable.
	 *
	 * @since 1.2.0
	 * @see   \SilverAssist\GitHubUpdater\Updater::getPackageAssetUrl()
	 */
	private function get_package_asset_url( string $asset_path ): string {
		// Primary: derive from plugin_file (avoids __DIR__ autoloader bug).
		if ( ! empty( $this->plugin_file ) ) {
			$plugin_dir = wp_normalize_path( dirname( $this->plugin_file ) );
			$base_url   = rtrim( plugin_dir_url( $this->plugin_file ), '/' );

			// Standard Composer vendor path for this package.
			$vendor_relative = 'vendor/silverassist/wp-settings-hub';
			$full_path       = $plugin_dir . '/' . $vendor_relative;

			if ( is_dir( $full_path ) ) {
				$asset_file = $full_path . '/' . ltrim( $asset_path, '/' );
				if ( file_exists( $asset_file ) ) {
					return $base_url . '/' . $vendor_relative . '/' . ltrim( $asset_path, '/' );
				}
			}
		}

		// Fallback: resolve from __DIR__ (single-plugin / non-autoloaded scenario).
		$package_dir = wp_normalize_path( dirname( __DIR__ ) );
		$asset_file  = $package_dir . '/' . ltrim( $asset_path, '/' );

		if ( ! file_exists( $asset_file ) ) {
			return '';
		}

		$normalized_asset = wp_normalize_path( $asset_file );
		$url_candidate    = str_replace(
			wp_normalize_path( ABSPATH ),
			trailingslashit( site_url() ),
			$normalized_asset
		);

		// If the replacement did not change the path, ABSPATH was not a prefix.
		if ( $url_candidate === $normalized_asset ) {
			return '';
		}

		// Ensure we ended up with an HTTP(S) URL, not a filesystem path.
		if ( ! preg_match( '#^https?://#', $url_candidate ) ) {
			return '';
		}

		return $url_candidate;
	}

	/**
	 * Register the parent "Silver Assist" menu.
	 *
	 * Creates a top-level menu at position 80 with a Dashboard submenu, both gated
	 * by the capability from the silverassist_settings_hub_dashboard_capability filter.
	 */
	private function register_parent_menu(): void {
		// Register top-level menu.
		add_menu_page(
			__( 'Silver Assist', 'silverassist-settings-hub' ),
			__( 'Silver Assist', 'silverassist-settings-hub' ),
			$this->get_dashboard_capability(),
			self::PARENT_SLUG,
			array( $this, 'render_dashboard' ),
			'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjIwIiBoZWlnaHQ9IjE4MCIgdmlld0JveD0iMCAwIDIyMCAxODAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxwYXRoIGQ9Ik0yMTUuNTQgNjguOTg3MkwxMTkuMjE0IDIuOTk4NjVDMTEzLjM5IC0wLjk5OTU1IDEwNS43MDggLTAuOTk5NTUgOTkuODcxNCAyLjk5ODY1TDMuNTQ1MTMgNjguOTg3MkMtMy4wMzE5NSA3My40OTU4IDAuMTQ3Mzc2IDgzLjgwMTIgOC4xMTk5NiA4My44MDEySDI4LjE2NjdWMTY2Ljg2NEMyOC4xNjY3IDE3My44ODggMzMuODQ1OCAxNzkuNTc2IDQwLjg1OTcgMTc5LjU3Nkg4OS4zMjYyQzcwLjYxNDMgMTcxLjU0MyA1Ny40ODQ0IDE1Mi44NjQgNTcuNDg0NCAxMzIuMjY2VjYwLjM4MzJDNTcuNDg0NCA1NS40NzM1IDYxLjU4NiA1MS41MTE4IDY2LjU0OTEgNTEuNzc5MUM3MS4yMDg5IDUyLjAyMjIgNzQuNjkxNiA1Ni4yMDI3IDc0LjY5MTYgNjAuODY5M1Y5Ni4zMTg0Qzc0LjY5MTYgOTguMDA3NiA3Ni40NzU0IDk5LjA3NyA3Ny45NTU5IDk4LjI4NzFMNzguMDA0NCA5OC4yNjI4Qzc4LjczMjUgOTcuODczOSA3OS4yMDU3IDk3LjEzMjYgNzkuMjA1NyA5Ni4yOTRWMzYuNzgyOEM3OS4yMDU3IDMxLjg3MzIgODMuMzA3MyAyNy45MTE0IDg4LjI3MDUgMjguMTc4OEM5Mi45MzAyIDI4LjQyMTkgOTYuNDEyOSAzMi42MDIzIDk2LjQxMjkgMzcuMjY4OVY4OS43NDM4Qzk2LjQxMjkgOTEuMDgwNiA5Ny41Nzc5IDkyLjEzNzkgOTguOTAwNiA5MS45Njc3SDk4Ljk0OTFDMTAwLjA3OCA5MS44MjE5IDEwMC45MjcgOTAuODc0IDEwMC45MjcgODkuNzQzOFYzMC4wOTg5QzEwMC45MjcgMjUuMTg5MyAxMDUuMDI5IDIxLjIyNzUgMTA5Ljk5MiAyMS40OTQ5QzExNC42NTIgMjEuNzM3OSAxMTguMTM0IDI1LjkxODQgMTE4LjEzNCAzMC41ODVWODkuNzQzOEMxMTguMTM0IDkwLjg4NjEgMTE4Ljk4NCA5MS44MjE5IDEyMC4xMTIgOTEuOTY3N0gxMjAuMTYxQzEyMS40ODMgOTIuMTM3OSAxMjIuNjQ4IDkxLjA5MjcgMTIyLjY0OCA4OS43NTZWMzguMzc0OEMxMjIuNjQ4IDMzLjQ2NTIgMTI2Ljc1IDI5LjUwMzQgMTMxLjcxMyAyOS43NzA4QzEzNi4zNzMgMzAuMDEzOCAxMzkuODU2IDM0LjE5NDMgMTM5Ljg1NiAzOC44NjA5Vjk2LjI4MTlDMTM5Ljg1NiA5Ny4xMDgzIDE0MC4zMjkgOTcuODYxNyAxNDEuMDU3IDk4LjI1MDZMMTQxLjEwNSA5OC4yNzQ5QzE0Mi41OTggOTkuMDY0OCAxNDQuMzcgOTcuOTk1NCAxNDQuMzcgOTYuMzA2MlY4MS4yNzM1QzE0NC4zNyA3Ni42MDY5IDE0Ny44NjUgNzIuNDI2NCAxNTIuNTEyIDcyLjE4MzNDMTU3LjQ3NSA3MS45MjgxIDE2MS41NzcgNzUuODc3NyAxNjEuNTc3IDgwLjc4NzNWMTMyLjI0MUMxNjEuNTc3IDE1Mi44NCAxNDguNDQ3IDE3MS41MTkgMTI5LjczNSAxNzkuNTUxSDE3OC4yMDJDMTg1LjIxNiAxNzkuNTUxIDE5MC44OTUgMTczLjg2NCAxOTAuODk1IDE2Ni44NFY4My44MDEySDIxMC45MjlDMjE4LjkwMiA4My44MDEyIDIyMi4wODEgNzMuNDgzNiAyMTUuNTA0IDY4Ljk4NzJIMjE1LjU0WiIgZmlsbD0iYmxhY2siLz4KPHBhdGggZD0iTTE0MC44OTkgMTExLjg4NkMxMzMuMDEyIDEwMy45ODcgMTIwLjIyMiAxMDMuOTg3IDExMi4zNDYgMTExLjg4NkwxMDkuNTU1IDExNC42ODFMMTA2Ljc2NCAxMTEuODg2Qzk4Ljg3NjUgMTAzLjk4NyA4Ni4wODY0IDEwMy45ODcgNzguMjEwOSAxMTEuODg2QzcwLjMyMzIgMTE5Ljc4NSA3MC4zMjMyIDEzMi41OTQgNzguMjEwOSAxNDAuNDgxTDEwOS41NjcgMTcxLjg4M0wxMjYuMTY4IDE1NS4yNThMMTQwLjkyNCAxNDAuNDgxQzE0OC44MTEgMTMyLjU4MiAxNDguODExIDExOS43NzMgMTQwLjkyNCAxMTEuODg2SDE0MC44OTlaIiBmaWxsPSJibGFjayIvPgo8L3N2Zz4=',
			80
		);

		// Add Dashboard as first submenu (this removes the duplicate parent link).
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Dashboard', 'silverassist-settings-hub' ),
			__( 'Dashboard', 'silverassist-settings-hub' ),
			$this->get_dashboard_capability(),
			self::PARENT_SLUG,
			array( $this, 'render_dashboard' )
		);
	}

	/**
	 * Register submenu items for all registered plugins.
	 *
	 * Skips slugs whose page was already added, so repeated passes never duplicate a row.
	 */
	private function register_submenus(): void {
		foreach ( $this->plugins as $plugin ) {
			if ( isset( $this->registered_submenus[ $plugin['slug'] ] ) ) {
				continue;
			}

			$this->registered_submenus[ $plugin['slug'] ] = true;

			add_submenu_page(
				self::PARENT_SLUG,
				$plugin['name'],
				$plugin['tab_title'] ?? $plugin['name'],
				$this->get_plugin_capability( $plugin ),
				$plugin['slug'],
				function () use ( $plugin ): void {
					$this->render_plugin_page( $plugin );
				}
			);
		}
	}

	/**
	 * Normalise a capability name, falling back to the default when it is not usable.
	 *
	 * Fails closed: an invalid value never opens a page to more users than the default would.
	 * Only primitive capabilities and role-style capabilities are supported; meta
	 * capabilities that need an object ID (for example edit_post) cannot be checked here.
	 *
	 * @since 1.3.0
	 *
	 * @param mixed  $capability Raw capability, or null when the caller did not pass one.
	 * @param string $context    Plugin slug (or menu slug) used in the notice.
	 *
	 * @return string A usable capability name.
	 */
	private function normalize_capability( mixed $capability, string $context ): string {
		if ( null === $capability ) {
			return self::DEFAULT_CAPABILITY;
		}

		if ( is_string( $capability ) ) {
			// Not sanitize_key(): it lowercases, which would silently break a mixed-case custom capability.
			$capability = trim( $capability );

			if ( 1 === preg_match( '/^[A-Za-z0-9_-]+$/', $capability ) ) {
				return $capability;
			}
		}

		_doing_it_wrong(
			__METHOD__,
			sprintf(
				'Invalid "capability" for Settings Hub entry "%1$s"; falling back to "%2$s".',
				esc_html( $context ),
				esc_html( self::DEFAULT_CAPABILITY )
			),
			'1.3.0'
		);

		return self::DEFAULT_CAPABILITY;
	}

	/**
	 * Resolve the capability a user needs to see and open a plugin's page.
	 *
	 * @since 1.3.0
	 *
	 * @param array<string, mixed> $plugin Stored plugin data.
	 *
	 * @return string Capability name.
	 */
	private function get_plugin_capability( array $plugin ): string {
		$slug       = (string) ( $plugin['slug'] ?? '' );
		$capability = $this->normalize_capability( $plugin['capability'] ?? null, $slug );

		/**
		 * Filters the capability required to see and open a plugin's Settings Hub page.
		 *
		 * The capability gates the plugin's submenu item, dashboard card, tab and page.
		 * It starts from the `capability` the plugin passed to register_plugin(), or
		 * manage_options. An invalid return value falls back to manage_options.
		 *
		 * @since 1.3.0
		 *
		 * @param string               $capability Resolved capability.
		 * @param string               $slug       Plugin slug.
		 * @param array<string, mixed> $plugin     Stored plugin data.
		 */
		return $this->normalize_capability(
			apply_filters( 'silverassist_settings_hub_plugin_capability', $capability, $slug, $plugin ),
			$slug
		);
	}

	/**
	 * Resolve the capability required for the parent menu and the Dashboard.
	 *
	 * @since 1.3.0
	 *
	 * @return string Capability name.
	 */
	private function get_dashboard_capability(): string {
		/**
		 * Filters the capability required for the Silver Assist menu and its Dashboard.
		 *
		 * The Dashboard tab is hidden from users who lack it.
		 *
		 * Plugins registered with a lower capability stay reachable without it: WordPress
		 * links the top-level menu to the first submenu page the user may open. An invalid
		 * return value falls back to manage_options.
		 *
		 * @since 1.3.0
		 *
		 * @param string $capability Capability, manage_options by default.
		 */
		return $this->normalize_capability(
			apply_filters( 'silverassist_settings_hub_dashboard_capability', self::DEFAULT_CAPABILITY ),
			self::PARENT_SLUG
		);
	}

	/**
	 * Get the registered plugins the current user may open.
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, array{
	 *     name: string,
	 *     slug: string,
	 *     callback: callable,
	 *     capability: string,
	 *     description?: string,
	 *     version?: string,
	 *     tab_title?: string,
	 *     actions?: array<array{label: string, url?: string, callback?: callable, class?: string}>
	 * }> Visible plugins, keyed by slug.
	 */
	private function get_visible_plugins(): array {
		return array_filter(
			$this->plugins,
			fn( array $plugin ): bool => current_user_can( $this->get_plugin_capability( $plugin ) )
		);
	}

	/**
	 * Render the main dashboard page.
	 *
	 * Shows all registered plugins with cards containing name, description,
	 * version, and a link to their settings page.
	 */
	public function render_dashboard(): void {
		$plugins = $this->get_visible_plugins();

		// Button ids only need to be unique within one rendered page.
		$this->action_button_ids = array();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Silver Assist', 'silverassist-settings-hub' ); ?></h1>

			<?php if ( $this->render_tabs ) : ?>
				<?php $this->render_tabs_navigation( '' ); ?>
			<?php endif; ?>

			<p class="silverassist-dashboard-description">
				<?php esc_html_e( 'Welcome to Silver Assist! Below are all your installed Silver Assist plugins.', 'silverassist-settings-hub' ); ?>
				<?php if ( ! empty( $plugins ) ) : ?>
					<span class="silverassist-dashboard-count">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of registered Silver Assist plugins. */
								_n( '%d plugin', '%d plugins', count( $plugins ), 'silverassist-settings-hub' ),
								count( $plugins )
							)
						);
						?>
					</span>
				<?php endif; ?>
			</p>

			<?php if ( empty( $plugins ) ) : ?>
				<div class="silverassist-empty-state">
					<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
					<p>
						<?php esc_html_e( 'No Silver Assist plugins have been registered yet.', 'silverassist-settings-hub' ); ?>
					</p>
					<p class="silverassist-empty-state-hint">
						<?php esc_html_e( 'Activate a Silver Assist plugin and it will show up here automatically.', 'silverassist-settings-hub' ); ?>
					</p>
				</div>
			<?php else : ?>
				<div class="silverassist-dashboard-grid">
					<?php foreach ( $plugins as $plugin ) : ?>
						<?php $this->render_plugin_card( $plugin ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a plugin card on the dashboard.
	 *
	 * @param array<string, string|callable|array<array<string, string|callable>>> $plugin {
	 *     Plugin data array.
	 *
	 *     @type string   $name        Plugin name.
	 *     @type string   $slug        Plugin slug.
	 *     @type callable $callback    Settings page callback.
	 *     @type string   $capability  Capability required to open the plugin's page.
	 *     @type string   $description Optional. Plugin description.
	 *     @type string   $version     Optional. Plugin version.
	 *     @type string   $tab_title   Optional. Tab title.
	 *     @type array[]  $actions     Optional. Action buttons.
	 * }
	 */
	private function render_plugin_card( array $plugin ): void {
		$slug         = (string) $plugin['slug'];
		$settings_url = admin_url( 'admin.php?page=' . $slug );
		$name         = (string) $plugin['name'];
		$description  = $plugin['description'] ?? '';
		$version      = $plugin['version'] ?? '';
		$heading_id   = 'silverassist-card-' . sanitize_key( $slug ) . '-title';
		?>
		<article class="silverassist-plugin-card" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
			<div class="card-header">
				<h2 id="<?php echo esc_attr( $heading_id ); ?>">
					<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
					<?php echo esc_html( $name ); ?>
				</h2>
				<?php if ( ! empty( $version ) ) : ?>
					<span class="silverassist-version-badge">
						v<?php echo esc_html( (string) $version ); ?>
					</span>
				<?php endif; ?>
			</div>

			<div class="card-content">
				<?php if ( ! empty( $description ) ) : ?>
					<p class="card-description">
						<?php echo esc_html( (string) $description ); ?>
					</p>
				<?php endif; ?>

				<div class="plugin-actions">
					<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary">
						<?php esc_html_e( 'Configure', 'silverassist-settings-hub' ); ?>
					</a>

					<?php
					// Render additional actions from plugins.
					if ( isset( $plugin['actions'] ) && is_array( $plugin['actions'] ) && count( $plugin['actions'] ) > 0 ) {
						foreach ( $plugin['actions'] as $action ) {
							$this->render_action_button( $action, $plugin['slug'] );
						}
					}
					/**
					 * Fires after the Configure button and the buttons from `actions` in a plugin card.
					 *
					 * Allows plugins to add custom action buttons to their dashboard card.
					 * It fires once per visible plugin card, inside the card's actions element.
					 *
					 * @since 1.1.0
					 *
					 * @param string               $slug   The plugin slug.
					 * @param array<string, mixed> $plugin The stored plugin data (without plugin_file).
					 */
					do_action( 'silverassist_settings_hub_plugin_actions', $plugin['slug'], $plugin );
					?>
				</div>
			</div>
		</article>
		<?php
	}

	/**
	 * Render an action button.
	 *
	 * @param array{label: string, url?: string, callback?: callable, class?: string} $action       Action data.
	 * @param string                                                                  $plugin_slug Plugin slug for callback context.
	 */
	private function render_action_button( array $action, string $plugin_slug ): void {
		if ( empty( $action['label'] ) ) {
			return;
		}

		$class = $action['class'] ?? 'button';

		// If URL is provided, render as link.
		if ( ! empty( $action['url'] ) ) {
			?>
			<a href="<?php echo esc_url( $action['url'] ); ?>" class="<?php echo esc_attr( $class ); ?>">
				<?php echo esc_html( $action['label'] ); ?>
			</a>
			<?php
			return;
		}

		// If a callback is provided, render a button. The callback runs now, while the page renders,
		// and whatever JavaScript it prints becomes the body of the button's click handler,
		// where `event` is the click event.
		if ( isset( $action['callback'] ) && is_callable( $action['callback'] ) ) {
			$button_id = $this->unique_button_id( 'sa-action-' . sanitize_key( $plugin_slug . '-' . $action['label'] ) );
			?>
			<button 
				type="button" 
				id="<?php echo esc_attr( $button_id ); ?>" 
				class="<?php echo esc_attr( $class ); ?>"
				data-plugin="<?php echo esc_attr( $plugin_slug ); ?>"
			>
				<?php echo esc_html( $action['label'] ); ?>
			</button>
			<script type="text/javascript">
				document.getElementById('<?php echo esc_js( $button_id ); ?>').addEventListener('click', function(event) {
					<?php call_user_func( $action['callback'], $plugin_slug ); ?>
				});
			</script>
			<?php
		}
	}

	/**
	 * Make a button id unique within the page being rendered.
	 *
	 * The sanitize_key() call drops spaces, so the labels "Run Now" and "RunNow" would share an id.
	 * The first button keeps the plain id; later ones get a numeric suffix.
	 *
	 * @param string $base_id Id built from the plugin slug and label.
	 *
	 * @return string
	 */
	private function unique_button_id( string $base_id ): string {
		$this->action_button_ids[ $base_id ] = ( $this->action_button_ids[ $base_id ] ?? 0 ) + 1;
		$count                               = $this->action_button_ids[ $base_id ];

		return 1 === $count ? $base_id : $base_id . '-' . $count;
	}

	/**
	 * Render a plugin's settings page with optional tabs navigation.
	 *
	 * @param array<string, string|callable|array<array<string, string|callable>>> $plugin {
	 *     Plugin data array.
	 *
	 *     @type string   $name        Plugin name.
	 *     @type string   $slug        Plugin slug.
	 *     @type callable $callback    Settings page callback.
	 *     @type string   $capability  Capability required to open the plugin's page.
	 *     @type string   $description Optional. Plugin description.
	 *     @type string   $version     Optional. Plugin version.
	 *     @type string   $tab_title   Optional. Tab title.
	 *     @type array[]  $actions     Optional. Action buttons.
	 * }
	 */
	private function render_plugin_page( array $plugin ): void {
		// WordPress already gates the page through its submenu capability; this covers direct calls.
		if ( ! current_user_can( $this->get_plugin_capability( $plugin ) ) ) {
			wp_die(
				esc_html__( 'You are not allowed to access this page.', 'silverassist-settings-hub' ),
				esc_html__( 'Access denied', 'silverassist-settings-hub' ),
				array( 'response' => 403 )
			);
		}

		$version = $plugin['version'] ?? '';
		?>
		<div class="wrap">
			<h1>
				<?php echo esc_html( $plugin['name'] ); ?>
				<?php if ( ! empty( $version ) ) : ?>
					<span class="version-badge">v<?php echo esc_html( (string) $version ); ?></span>
				<?php endif; ?>
			</h1>

			<?php if ( $this->render_tabs ) : ?>
				<?php $this->render_tabs_navigation( $plugin['slug'] ); ?>
			<?php endif; ?>

			<?php call_user_func( $plugin['callback'] ); ?>
		</div>
		<?php
	}

	/**
	 * Render tabs navigation for cross-plugin navigation.
	 *
	 * @param string $active_slug Currently active plugin slug (empty for dashboard).
	 */
	private function render_tabs_navigation( string $active_slug ): void {
		$plugins = $this->get_visible_plugins();

		if ( empty( $plugins ) ) {
			return;
		}

		$dashboard_url       = admin_url( 'admin.php?page=' . self::PARENT_SLUG );
		$is_dashboard_active = empty( $active_slug );
		// The Dashboard needs its own capability; for anyone else the tab would be a dead link.
		$show_dashboard = current_user_can( $this->get_dashboard_capability() );
		?>
		<nav class="nav-tab-wrapper silverassist-hub-tabs" aria-label="<?php esc_attr_e( 'Silver Assist', 'silverassist-settings-hub' ); ?>">
			<?php if ( $show_dashboard ) : ?>
				<a href="<?php echo esc_url( $dashboard_url ); ?>" class="nav-tab <?php echo $is_dashboard_active ? 'nav-tab-active' : ''; ?>"<?php echo $is_dashboard_active ? ' aria-current="page"' : ''; ?>>
					<?php esc_html_e( 'Dashboard', 'silverassist-settings-hub' ); ?>
				</a>
			<?php endif; ?>

			<?php foreach ( $plugins as $plugin ) : ?>
				<?php
				$tab_url   = admin_url( 'admin.php?page=' . $plugin['slug'] );
				$is_active = $plugin['slug'] === $active_slug;
				$tab_title = $plugin['tab_title'] ?? $plugin['name'];
				?>
				<a href="<?php echo esc_url( $tab_url ); ?>" class="nav-tab <?php echo $is_active ? 'nav-tab-active' : ''; ?>"<?php echo $is_active ? ' aria-current="page"' : ''; ?>>
					<?php echo esc_html( $tab_title ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Get all registered plugins.
	 *
	 * @return array<string, array{
	 *     name: string,
	 *     slug: string,
	 *     callback: callable,
	 *     capability: string,
	 *     description?: string,
	 *     version?: string,
	 *     tab_title?: string,
	 *     actions?: array<array{label: string, url?: string, callback?: callable, class?: string}>
	 * }> Registered plugins.
	 */
	public function get_plugins(): array {
		return $this->plugins;
	}

	/**
	 * Check if a plugin is registered.
	 *
	 * @param string $slug Plugin slug.
	 *
	 * @return bool True if registered, false otherwise.
	 */
	public function is_plugin_registered( string $slug ): bool {
		return isset( $this->plugins[ $slug ] );
	}

	/**
	 * Get the parent menu slug.
	 *
	 * @return string Parent menu slug.
	 */
	public function get_parent_slug(): string {
		return self::PARENT_SLUG;
	}

	/**
	 * Check if tabs are enabled.
	 *
	 * @return bool True if tabs are enabled, false otherwise.
	 */
	public function is_tabs_enabled(): bool {
		return $this->render_tabs;
	}

	/**
	 * Prevent cloning of the singleton instance.
	 *
	 * @return void
	 */
	private function __clone() {
		// Singleton - cannot clone.
	}

	/**
	 * Prevent unserialization of the singleton instance.
	 *
	 * @throws \LogicException Always: a second instance would break the singleton.
	 */
	public function __wakeup(): void {
		throw new \LogicException( 'The Settings Hub singleton cannot be unserialized.' );
	}
}
