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
 * Provides a unified Settings > Silver Assist menu with auto-registration,
 * dynamic dashboard, and optional cross-plugin navigation tabs.
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
	 *     icon_url?: string,
	 *     description?: string,
	 *     version?: string,
	 *     tab_title?: string
	 * }>
	 */
	private array $plugins = [];

	/**
	 * Parent menu slug.
	 *
	 * @var string
	 */
	private const PARENT_SLUG = 'silver-assist';

	/**
	 * Whether the parent menu has been registered.
	 *
	 * @var bool
	 */
	private bool $parent_registered = false;

	/**
	 * Whether to render tabs for cross-plugin navigation.
	 *
	 * @var bool
	 */
	private bool $render_tabs = true;

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
	 * Automatically creates the parent "Silver Assist" menu if it doesn't exist,
	 * and adds the plugin as a submenu item.
	 *
	 * @param string   $slug        Unique plugin slug (e.g., 'post-revalidate').
	 * @param string   $name        Display name for the plugin.
	 * @param callable $callback    Function to render the plugin's settings page.
	 * @param array{
	 *     icon_url?: string,
	 *     description?: string,
	 *     version?: string,
	 *     tab_title?: string
	 * } $args Optional arguments for the plugin.
	 *
	 * @return void
	 */
	public function register_plugin( string $slug, string $name, callable $callback, array $args = [] ): void {
		// Store plugin data.
		$this->plugins[ $slug ] = array_merge(
			[
				'name'     => $name,
				'slug'     => $slug,
				'callback' => $callback,
			],
			$args
		);

		// Hook into admin_menu to register menus.
		add_action( 'admin_menu', [ $this, 'register_menus' ], 5 );
	}

	/**
	 * Enable or disable tab rendering.
	 *
	 * @param bool $enable Whether to render tabs.
	 *
	 * @return void
	 */
	public function enable_tabs( bool $enable ): void {
		$this->render_tabs = $enable;
	}

	/**
	 * Register parent and submenu items.
	 *
	 * Called on admin_menu hook with priority 5 to ensure parent exists
	 * before plugins register their submenus.
	 *
	 * @internal
	 *
	 * @return void
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
	 * Register the parent "Silver Assist" menu.
	 *
	 * @return void
	 */
	private function register_parent_menu(): void {
		add_options_page(
			__( 'Silver Assist', 'silverassist-settings-hub' ),
			__( 'Silver Assist', 'silverassist-settings-hub' ),
			'manage_options',
			self::PARENT_SLUG,
			[ $this, 'render_dashboard' ],
			null
		);
	}

	/**
	 * Register submenu items for all registered plugins.
	 *
	 * @return void
	 */
	private function register_submenus(): void {
		foreach ( $this->plugins as $plugin ) {
			add_submenu_page(
				self::PARENT_SLUG,
				$plugin['name'],
				$plugin['tab_title'] ?? $plugin['name'],
				'manage_options',
				$plugin['slug'],
				function () use ( $plugin ) {
					$this->render_plugin_page( $plugin );
				}
			);
		}
	}

	/**
	 * Render the main dashboard page.
	 *
	 * Shows all registered plugins with cards containing name, description,
	 * version, and a link to their settings page.
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Silver Assist', 'silverassist-settings-hub' ); ?></h1>

			<?php if ( $this->render_tabs ) : ?>
				<?php $this->render_tabs_navigation( '' ); ?>
			<?php endif; ?>

			<p class="description">
				<?php esc_html_e( 'Welcome to Silver Assist! Below are all your installed Silver Assist plugins.', 'silverassist-settings-hub' ); ?>
			</p>

			<?php if ( empty( $this->plugins ) ) : ?>
				<div class="notice notice-info">
					<p>
						<?php esc_html_e( 'No Silver Assist plugins have been registered yet.', 'silverassist-settings-hub' ); ?>
					</p>
				</div>
			<?php else : ?>
				<div class="silverassist-dashboard-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
					<?php foreach ( $this->plugins as $plugin ) : ?>
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
	 * @param array{
	 *     name: string,
	 *     slug: string,
	 *     callback: callable,
	 *     icon_url?: string,
	 *     description?: string,
	 *     version?: string,
	 *     tab_title?: string
	 * } $plugin Plugin data.
	 *
	 * @return void
	 */
	private function render_plugin_card( array $plugin ): void {
		$settings_url = admin_url( 'options-general.php?page=' . $plugin['slug'] );
		?>
		<div class="card" style="padding: 20px;">
			<?php if ( ! empty( $plugin['icon_url'] ) ) : ?>
				<div style="margin-bottom: 15px;">
					<img src="<?php echo esc_url( $plugin['icon_url'] ); ?>" alt="<?php echo esc_attr( $plugin['name'] ); ?>" style="width: 48px; height: 48px;">
				</div>
			<?php endif; ?>

			<h2 style="margin: 0 0 10px 0; font-size: 16px;">
				<?php echo esc_html( $plugin['name'] ); ?>
				<?php if ( ! empty( $plugin['version'] ) ) : ?>
					<span style="font-size: 12px; color: #666; font-weight: normal;">
						v<?php echo esc_html( $plugin['version'] ); ?>
					</span>
				<?php endif; ?>
			</h2>

			<?php if ( ! empty( $plugin['description'] ) ) : ?>
				<p style="margin: 0 0 15px 0; color: #666;">
					<?php echo esc_html( $plugin['description'] ); ?>
				</p>
			<?php endif; ?>

			<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary">
				<?php esc_html_e( 'Configure', 'silverassist-settings-hub' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Render a plugin's settings page with optional tabs navigation.
	 *
	 * @param array{
	 *     name: string,
	 *     slug: string,
	 *     callback: callable,
	 *     icon_url?: string,
	 *     description?: string,
	 *     version?: string,
	 *     tab_title?: string
	 * } $plugin Plugin data.
	 *
	 * @return void
	 */
	private function render_plugin_page( array $plugin ): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $plugin['name'] ); ?></h1>

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
	 *
	 * @return void
	 */
	private function render_tabs_navigation( string $active_slug ): void {
		if ( empty( $this->plugins ) ) {
			return;
		}

		$dashboard_url    = admin_url( 'options-general.php?page=' . self::PARENT_SLUG );
		$is_dashboard_active = empty( $active_slug );
		?>
		<nav class="nav-tab-wrapper" style="margin-bottom: 20px;">
			<a href="<?php echo esc_url( $dashboard_url ); ?>" class="nav-tab <?php echo $is_dashboard_active ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Dashboard', 'silverassist-settings-hub' ); ?>
			</a>

			<?php foreach ( $this->plugins as $plugin ) : ?>
				<?php
				$tab_url      = admin_url( 'options-general.php?page=' . $plugin['slug'] );
				$is_active    = $plugin['slug'] === $active_slug;
				$tab_title    = $plugin['tab_title'] ?? $plugin['name'];
				?>
				<a href="<?php echo esc_url( $tab_url ); ?>" class="nav-tab <?php echo $is_active ? 'nav-tab-active' : ''; ?>">
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
	 *     icon_url?: string,
	 *     description?: string,
	 *     version?: string,
	 *     tab_title?: string
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
	 * @return void
	 */
	public function __wakeup() {
		// Singleton - cannot unserialize.
	}
}
