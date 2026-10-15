<?php
/**
 * Tests for the Phase 4 hardening of the Settings Hub.
 *
 * @package SilverAssist\SettingsHub\Tests
 */

declare(strict_types=1);

namespace SilverAssist\SettingsHub\Tests\Unit;

use SilverAssist\SettingsHub\SettingsHub;
use SilverAssist\SettingsHub\Tests\TestCase;

/**
 * Singleton guards, the action-button contract, the packaged version and the
 * stylesheet rules that guard against dead code.
 *
 * @covers \SilverAssist\SettingsHub\SettingsHub
 */
final class HardeningTest extends TestCase {
	/**
	 * Render one action button through the hub.
	 *
	 * @param array<string, mixed> $action      Action data.
	 * @param string               $plugin_slug Plugin slug.
	 *
	 * @return string
	 */
	private function render_button( array $action, string $plugin_slug = 'my-plugin' ): string {
		return $this->render(
			function () use ( $action, $plugin_slug ): void {
				$this->call_hub_method( 'render_action_button', array( $action, $plugin_slug ) );
			}
		);
	}

	/**
	 * Ids of the buttons in a rendered fragment.
	 *
	 * @param string $html Rendered fragment.
	 *
	 * @return string[]
	 */
	private function button_ids( string $html ): array {
		preg_match_all( '/<button[^>]*\sid="([^"]+)"/', $html, $matches );

		return $matches[1];
	}

	/**
	 * Unserializing the hub is refused.
	 *
	 * @return void
	 */
	public function test_unserialize_is_rejected(): void {
		$payload = serialize( $this->hub() );
		$thrown  = null;

		try {
			unserialize( $payload ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
		} catch ( \LogicException $exception ) { // @phpstan-ignore catch.neverThrown (the hub throws it from __wakeup, which PHPStan does not follow)
			$thrown = $exception;
		}

		$this->assertInstanceOf( \LogicException::class, $thrown, 'unserialize() must not return a second hub' );
	}

	/**
	 * Cloning the hub is not possible from outside.
	 *
	 * @return void
	 */
	public function test_clone_is_private(): void {
		$this->assertTrue( ( new \ReflectionMethod( SettingsHub::class, '__clone' ) )->isPrivate() );
		$this->assertTrue( ( new \ReflectionMethod( SettingsHub::class, '__construct' ) )->isPrivate() );
	}

	/**
	 * The stylesheet version is the newest released CHANGELOG version.
	 *
	 * @return void
	 */
	public function test_package_version_matches_changelog(): void {
		$changelog = (string) file_get_contents( dirname( __DIR__, 2 ) . '/CHANGELOG.md' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertSame( 1, preg_match( '/^## \[(\d+\.\d+\.\d+)\]/m', $changelog, $matches ), 'CHANGELOG.md has no released version heading' );

		$version = ( new \ReflectionClassConstant( SettingsHub::class, 'PACKAGE_VERSION' ) )->getValue();

		$this->assertSame( $matches[1] ?? '', $version, 'PACKAGE_VERSION must equal the newest released CHANGELOG heading (scripts/update-version.sh keeps them in step).' );
	}

	/**
	 * A callback action runs its output in the click handler, with the event in scope.
	 *
	 * @return void
	 */
	public function test_callback_action_handler_receives_the_event(): void {
		$output = $this->render_button(
			array(
				'label'    => 'Run Now',
				'callback' => static function ( string $slug ): void {
					echo 'event.preventDefault(); window.__ran = "' . $slug . '";'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				},
			)
		);

		$this->assertMatchesRegularExpression( "/addEventListener\\('click', function\\(\\s*event\\s*\\)/", $output );
		$this->assertStringContainsString( 'event.preventDefault(); window.__ran = "my-plugin";', $output );
	}

	/**
	 * The callback runs while the page renders, not when the button is clicked.
	 *
	 * @return void
	 */
	public function test_callback_action_runs_once_at_render_time(): void {
		$calls = 0;
		$this->render_button(
			array(
				'label'    => 'Go',
				'callback' => static function () use ( &$calls ): void {
					++$calls;
				},
			)
		);

		$this->assertSame( 1, $calls );
	}

	/**
	 * Labels that sanitise to the same key still get distinct button ids.
	 *
	 * @return void
	 */
	public function test_colliding_action_ids_are_made_unique(): void {
		$noop = static function (): void {};
		$html = $this->render_button( array( 'label' => 'Run Now', 'callback' => $noop ) )
			. $this->render_button( array( 'label' => 'RunNow', 'callback' => $noop ) )
			. $this->render_button( array( 'label' => 'Run Now', 'callback' => $noop ) );

		$ids = $this->button_ids( $html );

		$this->assertCount( 3, $ids );
		$this->assertCount( 3, array_unique( $ids ), 'Button ids must be unique in one page: ' . implode( ', ', $ids ) );
		$this->assertSame( 'sa-action-my-plugin-runnow', $ids[0], 'The first button keeps the documented id' );
		foreach ( $ids as $id ) {
			// Each script must target its own button.
			$this->assertSame( 1, substr_count( $html, "getElementById('{$id}')" ), "No script targets {$id} exactly once." );
		}
	}

	/**
	 * Rendering the dashboard again starts the ids over.
	 *
	 * @return void
	 */
	public function test_button_ids_are_unique_per_dashboard_render_not_across_renders(): void {
		$this->login_as( 'administrator' );
		$this->hub()->register_plugin(
			'my-plugin',
			'My Plugin',
			'__return_true',
			array(
				'actions' => array(
					array(
						'label'    => 'Run Now',
						'callback' => static function (): void {},
					),
				),
			)
		);

		$first  = $this->render( array( $this->hub(), 'render_dashboard' ) );
		$second = $this->render( array( $this->hub(), 'render_dashboard' ) );

		$this->assertSame( $this->button_ids( $first ), $this->button_ids( $second ) );
		$this->assertSame( array( 'sa-action-my-plugin-runnow' ), $this->button_ids( $first ) );
	}

	/**
	 * The stylesheet has no container query on the container itself.
	 *
	 * A query on the element that declares container-type never matches; the
	 * responsive behaviour is the auto-fill grid plus the max-width media query.
	 *
	 * @return void
	 */
	public function test_stylesheet_has_no_dead_container_query(): void {
		$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/settings-hub.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$this->assertStringNotContainsString( '@container', $css );
		$this->assertStringNotContainsString( 'container-type', $css );
		$this->assertStringContainsString( '@media (max-width: 782px)', $css, 'The media query is the working fallback' );
	}
}
