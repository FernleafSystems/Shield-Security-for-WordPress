<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Afs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class AfsUpgradeQueueingTest extends BaseUnitTest {

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_process_complete_queues_plugin_and_theme_update_scans_immediately() :void {
		$scans = new AfsUpgradeQueueingRecordingScans();
		$this->installController( $scans );
		$afs = new Afs();

		$afs->queueAssetScansFromUpgraderProcessComplete( null, [
			'action'  => 'update',
			'type'    => 'plugin',
			'plugins' => [
				'akismet/akismet.php',
				'hello-dolly/hello.php',
			],
		] );
		$afs->queueAssetScansFromUpgraderProcessComplete( null, [
			'action' => 'update',
			'type'   => 'theme',
			'themes' => [
				'twentytwentyfour',
			],
		] );

		$this->assertSame( [
			[ 'plugin', 'akismet/akismet.php' ],
			[ 'plugin', 'hello-dolly/hello.php' ],
			[ 'theme', 'twentytwentyfour' ],
		], $scans->queuedAssets );
	}

	public function test_post_install_defers_plugin_and_theme_queueing_until_shutdown() :void {
		$actions = [];
		Functions\when( 'add_action' )->alias(
			static function ( string $hook, callable $callback ) use ( &$actions ) :bool {
				$actions[ $hook ][] = $callback;
				return true;
			}
		);

		$scans = new AfsUpgradeQueueingRecordingScans();
		$this->installController( $scans );
		$afs = new Afs();
		$response = (object)[ 'destination' => 'asset-installed' ];

		$result = $afs->queueAssetScansFromUpgraderPostInstall( $response, [
			'plugin' => 'akismet/akismet.php',
			'theme'  => 'twentytwentyfour',
		] );

		$this->assertSame( $response, $result );
		$this->assertSame( [], $scans->queuedAssets );
		$this->assertCount( 1, $actions[ 'icwp-wpsf-pre_plugin_shutdown' ] ?? [] );

		$actions[ 'icwp-wpsf-pre_plugin_shutdown' ][ 0 ]();

		$this->assertSame( [
			[ 'plugin', 'akismet/akismet.php' ],
			[ 'theme', 'twentytwentyfour' ],
		], $scans->queuedAssets );
	}

	private function installController( AfsUpgradeQueueingRecordingScans $scans ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->cfg = (object)[
			'properties' => [
				'slug_parent' => 'icwp',
				'slug_plugin' => 'wpsf',
			],
		];
		$controller->comps = (object)[
			'scans' => $scans,
		];

		PluginControllerInstaller::install( $controller );
	}
}

class AfsUpgradeQueueingRecordingScans {

	public array $queuedAssets = [];

	public function startAfsAssetScan( string $assetType, string $assetKey, bool $resetIgnored = false ) :bool {
		unset( $resetIgnored );
		$this->queuedAssets[] = [ $assetType, $assetKey ];
		return true;
	}
}
