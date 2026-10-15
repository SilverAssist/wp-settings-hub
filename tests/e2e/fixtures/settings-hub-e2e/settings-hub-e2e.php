<?php
/**
 * Plugin Name: Settings Hub E2E Fixture
 * Description: Registers sample plugins with the Settings Hub so the design system can be tested in a real wp-admin. Not for production.
 * Version: 0.0.0
 *
 * @package SilverAssist\SettingsHub
 */

declare(strict_types=1);

use SilverAssist\SettingsHub\SettingsHub;

require_once WP_PLUGIN_DIR . '/wp-settings-hub/src/SettingsHub.php';

/**
 * Render a settings page body the way a consumer plugin would: WordPress-native markup.
 *
 * @param string $title Section title.
 */
function settings_hub_e2e_render_page( string $title ): void {
	?>
	<h2><?php echo esc_html( $title ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="e2e-field"><?php esc_html_e( 'Example setting', 'silverassist-settings-hub' ); ?></label></th>
			<td><input id="e2e-field" class="regular-text" type="text" value="" /></td>
		</tr>
	</table>
	<?php
}

add_action(
	'plugins_loaded',
	static function (): void {
		$hub = SettingsHub::get_instance();

		$hub->register_plugin(
			'e2e-security',
			'Security',
			static function (): void {
				settings_hub_e2e_render_page( 'Security settings' );
			},
			array(
				'description' => 'Login protection, headers and hardening for the site.',
				'version'     => '1.4.2',
				'actions'     => array(
					array(
						'label' => 'View logs',
						'url'   => admin_url( 'index.php' ),
					),
				),
			)
		);

		$hub->register_plugin(
			'e2e-updater',
			'GitHub Updater',
			static function (): void {
				settings_hub_e2e_render_page( 'Updater settings' );
			},
			array(
				'description' => 'Keeps private plugins updated from their GitHub releases.',
				'version'     => '2.0.0',
			)
		);

		// Long values: the card must wrap them without breaking the layout.
		$hub->register_plugin(
			'e2e-long',
			'A plugin with a deliberately very long display name that must wrap',
			static function (): void {
				settings_hub_e2e_render_page( 'Long settings' );
			},
			array(
				'description' => 'A long description that keeps going so the test can confirm that cards in the same row stay aligned and that the actions row stays pinned to the bottom of each card.',
				'version'     => '10.20.30',
				'tab_title'   => 'Long',
			)
		);
	},
	1
);

// ?sa_e2e=empty renders the dashboard with no registered plugins, to check the empty state.
add_action(
	'admin_menu',
	static function (): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['sa_e2e'] ) || 'empty' !== $_GET['sa_e2e'] ) {
			return;
		}

		$property = new ReflectionProperty( SettingsHub::class, 'plugins' );
		$property->setAccessible( true );
		$property->setValue( SettingsHub::get_instance(), array() );
	},
	6
);
