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
	 */
	private static ?SettingsHub $instance = null;

	/**
	 * Registered plugins.
	 *
	 * @var array<string, array{
	 *     name: string,
	 *     slug: string,
	 *     callback: callable,
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
	 * Whether the parent menu has been registered.
	 */
	private bool $parent_registered = false;

	/**
	 * Whether to render tabs for cross-plugin navigation.
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
	 *     description?: string,
	 *     version?: string,
	 *     tab_title?: string,
	 *     actions?: array<array{label: string, url?: string, callback?: callable, class?: string}>
	 * } $args Optional arguments for the plugin.
	 */
	public function register_plugin( string $slug, string $name, callable $callback, array $args = array() ): void {
		// Store plugin data.
		$this->plugins[ $slug ] = array_merge(
			array(
				'name'     => $name,
				'slug'     => $slug,
				'callback' => $callback,
			),
			$args
		);

		// Hook into admin_menu to register menus.
		add_action( 'admin_menu', array( $this, 'register_menus' ), 5 );
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
	 * Called on admin_menu hook with priority 5 to ensure parent exists
	 * before plugins register their submenus.
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
	 * Register the parent "Silver Assist" menu.
	 *
	 * Creates a top-level menu with a dashboard submenu.
	 */
	private function register_parent_menu(): void {
		// Register top-level menu.
		add_menu_page(
			__( 'Silver Assist', 'silverassist-settings-hub' ),
			__( 'Silver Assist', 'silverassist-settings-hub' ),
			'manage_options',
			self::PARENT_SLUG,
			array( $this, 'render_dashboard' ),
			'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjIwIiBoZWlnaHQ9IjE4MCIgdmlld0JveD0iMCAwIDIyMCAxODAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxwYXRoIGQ9Ik0yMTUuNTQgNjguOTg3MkwxMTkuMjE0IDIuOTk4NjVDMTEzLjM5IC0wLjk5OTU1IDEwNS43MDggLTAuOTk5NTUgOTkuODcxNCAyLjk5ODY1TDMuNTQ1MTMgNjguOTg3MkMtMy4wMzE5NSA3My40OTU4IDAuMTQ3Mzc2IDgzLjgwMTIgOC4xMTk5NiA4My44MDEySDI4LjE2NjdWMTY2Ljg2NEMyOC4xNjY3IDE3My44ODggMzMuODQ1OCAxNzkuNTc2IDQwLjg1OTcgMTc5LjU3Nkg4OS4zMjYyQzcwLjYxNDMgMTcxLjU0MyA1Ny40ODQ0IDE1Mi44NjQgNTcuNDg0NCAxMzIuMjY2VjYwLjM4MzJDNTcuNDg0NCA1NS40NzM1IDYxLjU4NiA1MS41MTE4IDY2LjU0OTEgNTEuNzc5MUM3MS4yMDg5IDUyLjAyMjIgNzQuNjkxNiA1Ni4yMDI3IDc0LjY5MTYgNjAuODY5M1Y5Ni4zMTg0Qzc0LjY5MTYgOTguMDA3NiA3Ni40NzU0IDk5LjA3NyA3Ny45NTU5IDk4LjI4NzFMNzguMDA0NCA5OC4yNjI4Qzc4LjczMjUgOTcuODczOSA3OS4yMDU3IDk3LjEzMjYgNzkuMjA1NyA5Ni4yOTRWMzYuNzgyOEM3OS4yMDU3IDMxLjg3MzIgODMuMzA3MyAyNy45MTE0IDg4LjI3MDUgMjguMTc4OEM5Mi45MzAyIDI4LjQyMTkgOTYuNDEyOSAzMi42MDIzIDk2LjQxMjkgMzcuMjY4OVY4OS43NDM4Qzk2LjQxMjkgOTEuMDgwNiA5Ny41Nzc5IDkyLjEzNzkgOTguOTAwNiA5MS45Njc3SDk4Ljk0OTFDMTAwLjA3OCA5MS44MjE5IDEwMC45MjcgOTAuODc0IDEwMC45MjcgODkuNzQzOFYzMC4wOTg5QzEwMC45MjcgMjUuMTg5MyAxMDUuMDI5IDIxLjIyNzUgMTA5Ljk5MiAyMS40OTQ5QzExNC42NTIgMjEuNzM3OSAxMTguMTM0IDI1LjkxODQgMTE4LjEzNCAzMC41ODVWODkuNzQzOEMxMTguMTM0IDkwLjg4NjEgMTE4Ljk4NCA5MS44MjE5IDEyMC4xMTIgOTEuOTY3N0gxMjAuMTYxQzEyMS40ODMgOTIuMTM3OSAxMjIuNjQ4IDkxLjA5MjcgMTIyLjY0OCA4OS43NTZWMzguMzc0OEMxMjIuNjQ4IDMzLjQ2NTIgMTI2Ljc1IDI5LjUwMzQgMTMxLjcxMyAyOS43NzA4QzEzNi4zNzMgMzAuMDEzOCAxMzkuODU2IDM0LjE5NDMgMTM5Ljg1NiAzOC44NjA5Vjk2LjI4MTlDMTM5Ljg1NiA5Ny4xMDgzIDE0MC4zMjkgOTcuODYxNyAxNDEuMDU3IDk4LjI1MDZMMTQxLjEwNSA5OC4yNzQ5QzE0Mi41OTggOTkuMDY0OCAxNDQuMzcgOTcuOTk1NCAxNDQuMzcgOTYuMzA2MlY4MS4yNzM1QzE0NC4zNyA3Ni42MDY5IDE0Ny44NjUgNzIuNDI2NCAxNTIuNTEyIDcyLjE4MzNDMTU3LjQ3NSA3MS45MjgxIDE2MS41NzcgNzUuODc3NyAxNjEuNTc3IDgwLjc4NzNWMTMyLjI0MUMxNjEuNTc3IDE1Mi44NCAxNDguNDQ3IDE3MS41MTkgMTI5LjczNSAxNzkuNTUxSDE3OC4yMDJDMTg1LjIxNiAxNzkuNTUxIDE5MC44OTUgMTczLjg2NCAxOTAuODk1IDE2Ni44NFY4My44MDEySDIxMC45MjlDMjE4LjkwMiA4My44MDEyIDIyMi4wODEgNzMuNDgzNiAyMTUuNTA0IDY4Ljk4NzJIMjE1LjU0WiIgZmlsbD0iIzAwRDFGRiIvPgo8cGF0aCBkPSJNMTQwLjg5OSAxMTEuODg2QzEzMy4wMTIgMTAzLjk4NyAxMjAuMjIyIDEwMy45ODcgMTEyLjM0NiAxMTEuODg2TDEwOS41NTUgMTE0LjY4MUwxMDYuNzY0IDExMS44ODZDOTguODc2NSAxMDMuOTg3IDg2LjA4NjQgMTAzLjk4NyA3OC4yMTA5IDExMS44ODZDNzAuMzIzMiAxMTkuNzg1IDcwLjMyMzIgMTMyLjU5NCA3OC4yMTA5IDE0MC40ODFMMTA5LjU2NyAxNzEuODgzTDEyNi4xNjggMTU1LjI1OEwxNDAuOTI0IDE0MC40ODFDMTQ4LjgxMSAxMzIuNTgyIDE0OC44MTEgMTE5Ljc3MyAxNDAuOTI0IDExMS44ODZIMTQWLJG5OVoiIGZpbGw9IndoaXRlIi8+Cjwvc3ZnPg==',
			80
		);

		// Add Dashboard as first submenu (this removes the duplicate parent link).
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Dashboard', 'silverassist-settings-hub' ),
			__( 'Dashboard', 'silverassist-settings-hub' ),
			'manage_options',
			self::PARENT_SLUG,
			array( $this, 'render_dashboard' )
		);
	}

	/**
	 * Register submenu items for all registered plugins.
	 */
	private function register_submenus(): void {
		foreach ( $this->plugins as $plugin ) {
			add_submenu_page(
				self::PARENT_SLUG,
				$plugin['name'],
				$plugin['tab_title'] ?? $plugin['name'],
				'manage_options',
				$plugin['slug'],
				function () use ( $plugin ): void {
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
	 *     description?: string,
	 *     version?: string,
	 *     tab_title?: string,
	 *     actions?: array<array{label: string, url?: string, callback?: callable, class?: string}>
	 * } $plugin Plugin data.
	 */
	private function render_plugin_card( array $plugin ): void {
		$settings_url = admin_url( 'admin.php?page=' . $plugin['slug'] );
		?>
		<div class="card" style="padding: 20px;">
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

			<div class="plugin-actions" style="display: flex; gap: 8px; flex-wrap: wrap;">
				<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Configure', 'silverassist-settings-hub' ); ?>
			</a>

			<?php
			// Render additional actions from plugins.
			if ( isset( $plugin['actions'] ) && is_array( $plugin['actions'] ) && count( $plugin['actions'] ) > 0 ) {
				foreach ( $plugin['actions'] as $action ) {
					$this->render_action_button( $action, $plugin['slug'] );
				}
			}               /**
				 * Fires after the default Configure button in a plugin card.
				 *
				 * Allows plugins to add custom action buttons to their dashboard card.
				 *
				 * @since 1.1.0
				 *
				 * @param string $slug   The plugin slug.
				 * @param array  $plugin The plugin data array.
				 */
				do_action( 'silverassist_settings_hub_plugin_actions', $plugin['slug'], $plugin );
			?>
			</div>
		</div>
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

		// If callback is provided, render as button with AJAX.
		if ( isset( $action['callback'] ) && is_callable( $action['callback'] ) ) {
			$button_id = 'sa-action-' . sanitize_key( $plugin_slug . '-' . $action['label'] );
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
				document.getElementById('<?php echo esc_js( $button_id ); ?>').addEventListener('click', function() {
					<?php call_user_func( $action['callback'], $plugin_slug ); ?>
				});
			</script>
			<?php
		}
	}

	/**
	 * Render a plugin's settings page with optional tabs navigation.
	 *
	 * @param array{
	 *     name: string,
	 *     slug: string,
	 *     callback: callable,
	 *     description?: string,
	 *     version?: string,
	 *     tab_title?: string,
	 *     actions?: array<array{label: string, url?: string, callback?: callable, class?: string}>
	 * } $plugin Plugin data.
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
	 */
	private function render_tabs_navigation( string $active_slug ): void {
		if ( empty( $this->plugins ) ) {
			return;
		}

		$dashboard_url       = admin_url( 'admin.php?page=' . self::PARENT_SLUG );
		$is_dashboard_active = empty( $active_slug );
		?>
		<nav class="nav-tab-wrapper" style="margin-bottom: 20px;">
			<a href="<?php echo esc_url( $dashboard_url ); ?>" class="nav-tab <?php echo $is_dashboard_active ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Dashboard', 'silverassist-settings-hub' ); ?>
			</a>

			<?php foreach ( $this->plugins as $plugin ) : ?>
				<?php
				$tab_url   = admin_url( 'admin.php?page=' . $plugin['slug'] );
				$is_active = $plugin['slug'] === $active_slug;
				$tab_title = $plugin['tab_title'] ?? $plugin['name'];
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
	 */
	public function __wakeup(): void {
		// Singleton - cannot unserialize.
	}
}
