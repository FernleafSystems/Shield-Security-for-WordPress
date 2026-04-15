<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Utilities\PluginReinstaller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\Plugins;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;

class PluginReinstallerTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_eligible_plugin_requires_installed_wporg_plugin_without_pending_update() :void {
		$eligiblePlugin = new PluginReinstallerTestPluginVo( 'akismet/akismet.php', true );
		ServicesState::installItems( [
			'service_wpplugins' => new PluginReinstallerTestPluginsService( [
				'plugin_vos' => [
					'akismet/akismet.php' => $eligiblePlugin,
					'premium/plugin.php'  => new PluginReinstallerTestPluginVo( 'premium/plugin.php', false ),
					'update/plugin.php'   => new PluginReinstallerTestPluginVo( 'update/plugin.php', true ),
				],
				'updates'    => [
					'update/plugin.php' => (object)[ 'new_version' => '2.0' ],
				],
			] ),
		] );

		$reinstaller = new PluginReinstaller();

		$this->assertSame( $eligiblePlugin, $reinstaller->eligiblePlugin( 'akismet/akismet.php' ) );
		$this->assertNull( $reinstaller->eligiblePlugin( 'missing/plugin.php' ) );
		$this->assertNull( $reinstaller->eligiblePlugin( 'premium/plugin.php' ) );
		$this->assertNull( $reinstaller->eligiblePlugin( 'update/plugin.php' ) );
	}

	public function test_reinstall_runs_only_for_eligible_plugin() :void {
		$plugins = new PluginReinstallerTestPluginsService( [
			'plugin_vos' => [
				'akismet/akismet.php' => new PluginReinstallerTestPluginVo( 'akismet/akismet.php', true ),
				'update/plugin.php'   => new PluginReinstallerTestPluginVo( 'update/plugin.php', true ),
			],
			'updates'    => [
				'update/plugin.php' => (object)[ 'new_version' => '2.0' ],
			],
		] );
		ServicesState::installItems( [
			'service_wpplugins' => $plugins,
		] );

		$reinstaller = new PluginReinstallerTestSubject();

		$this->assertTrue( $reinstaller->reinstall( 'akismet/akismet.php' ) );
		$this->assertFalse( $reinstaller->reinstall( 'update/plugin.php' ) );
		$this->assertSame( [ 'akismet/akismet.php' ], $plugins->reinstallCalls );
		$this->assertSame( [ 'akismet/akismet.php' ], $reinstaller->snapshotDeletes );
	}
}

class PluginReinstallerTestSubject extends PluginReinstaller {

	public array $snapshotDeletes = [];

	protected function deleteSnapshot( WpPluginVo $plugin ) :void {
		$this->snapshotDeletes[] = $plugin->file;
	}
}

class PluginReinstallerTestPluginsService extends Plugins {

	public array $reinstallCalls = [];

	private array $fixture;

	public function __construct( array $fixture ) {
		$this->fixture = \array_merge( [
			'plugin_vos' => [],
			'updates'    => [],
		], $fixture );
	}

	public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
		return $this->fixture[ 'plugin_vos' ][ $file ] ?? null;
	}

	public function isUpdateAvailable( $file ) :bool {
		return isset( $this->fixture[ 'updates' ][ $file ] );
	}

	public function reinstall( string $file, bool $useBackup = false ) :bool {
		$this->reinstallCalls[] = $file;
		return true;
	}
}

class PluginReinstallerTestPluginVo extends WpPluginVo {

	public string $file;
	public string $Name;
	public string $Title;

	private bool $isWpOrg;

	public function __construct( string $file, bool $isWpOrg ) {
		$this->file = $file;
		$this->Name = 'Test Plugin';
		$this->Title = 'Test Plugin';
		$this->isWpOrg = $isWpOrg;
	}

	public function __get( string $key ) {
		return $key === 'asset_type' ? 'plugin' : ( $this->{$key} ?? null );
	}

	public function isWpOrg() :bool {
		return $this->isWpOrg;
	}
}
