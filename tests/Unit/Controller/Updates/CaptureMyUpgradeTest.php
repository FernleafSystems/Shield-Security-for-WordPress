<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Controller\Updates;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Updates\CaptureMyUpgrade;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory
};

class CaptureMyUpgradeTest extends BaseUnitTest {

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_execute_registers_upgrade_capture_hooks() :void {
		$filters = [];
		$actions = [];
		Functions\when( 'add_filter' )->alias(
			static function ( string $hook, callable $callback, int $priority, int $acceptedArgs ) use ( &$filters ) :bool {
				$filters[] = [
					'hook'          => $hook,
					'callback'      => $callback,
					'priority'      => $priority,
					'accepted_args' => $acceptedArgs,
				];
				return true;
			}
		);
		Functions\when( 'add_action' )->alias(
			static function ( string $hook, callable $callback, int $priority, int $acceptedArgs ) use ( &$actions ) :bool {
				$actions[] = [
					'hook'          => $hook,
					'callback'      => $callback,
					'priority'      => $priority,
					'accepted_args' => $acceptedArgs,
				];
				return true;
			}
		);

		$this->installController();

		( new CaptureMyUpgrade() )->execute();

		$this->assertSame( 'upgrader_post_install', $filters[ 0 ][ 'hook' ] ?? null );
		$this->assertSame( 10, $filters[ 0 ][ 'priority' ] ?? null );
		$this->assertSame( 2, $filters[ 0 ][ 'accepted_args' ] ?? null );
		$this->assertSame( 'upgrader_process_complete', $actions[ 0 ][ 'hook' ] ?? null );
		$this->assertSame( 10, $actions[ 0 ][ 'priority' ] ?? null );
		$this->assertSame( 2, $actions[ 0 ][ 'accepted_args' ] ?? null );
	}

	public function test_capture_my_install_marks_matching_plugin_package() :void {
		$controller = $this->installController();

		$result = ( new CaptureMyUpgrade() )->captureMyInstall( true, [
			'plugin' => 'icwp-wpsf/icwp-wpsf.php',
		] );

		$this->assertTrue( $result );
		$this->assertTrue( $controller->is_my_upgrade );
	}

	public function test_capture_my_install_ignores_other_plugin_package() :void {
		$controller = $this->installController();

		( new CaptureMyUpgrade() )->captureMyInstall( true, [
			'plugin' => 'other-plugin/other-plugin.php',
		] );

		$this->assertFalse( $controller->is_my_upgrade );
	}

	public function test_capture_my_upgrade_marks_matching_bulk_update() :void {
		$controller = $this->installController();

		( new CaptureMyUpgrade() )->captureMyUpgrade( null, [
			'action'  => 'update',
			'type'    => 'plugin',
			'plugins' => [
				'other-plugin/other-plugin.php',
				'icwp-wpsf/icwp-wpsf.php',
			],
		] );

		$this->assertTrue( $controller->is_my_upgrade );
	}

	public function test_capture_my_upgrade_ignores_non_plugin_update_payloads() :void {
		$controller = $this->installController();

		( new CaptureMyUpgrade() )->captureMyUpgrade( null, [
			'action'  => 'install',
			'type'    => 'plugin',
			'plugins' => [ 'icwp-wpsf/icwp-wpsf.php' ],
		] );
		( new CaptureMyUpgrade() )->captureMyUpgrade( null, [
			'action'  => 'update',
			'type'    => 'theme',
			'plugins' => [ 'icwp-wpsf/icwp-wpsf.php' ],
		] );

		$this->assertFalse( $controller->is_my_upgrade );
	}

	private function installController() :object {
		return UnitTestControllerFactory::install( null, null, (object)[
			'base_file'     => 'icwp-wpsf/icwp-wpsf.php',
			'is_my_upgrade' => false,
		] );
	}
}
