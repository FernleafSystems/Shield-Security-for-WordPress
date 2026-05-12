<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Integrations\Lib\MainWP\Server\Data;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\{
	MWPSiteVO,
	SyncVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data\ClientPluginStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory
};

class ClientPluginStatusTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->returnArg();

		UnitTestControllerFactory::install(
			null,
			null,
			(object)[
				'base_file' => 'shield/shield.php',
				'cfg'       => new class {
					public function version() :string {
						return '18.2.1';
					}
				},
			]
		);
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_detect_covers_mainwp_status_matrix() :void {
		$cases = [
			'not_installed' => [
				'site'        => $this->makeSite( false, false ),
				'sync'        => [],
				'status_key'  => ClientPluginStatus::NOT_INSTALLED,
			],
			'inactive'      => [
				'site'        => $this->makeSite( true, false ),
				'sync'        => [],
				'status_key'  => ClientPluginStatus::INACTIVE,
			],
			'empty_sync'    => [
				'site'        => $this->makeSite( true, true ),
				'sync'        => [],
				'status_key'  => ClientPluginStatus::NEED_SYNC,
			],
			'legacy_sync'   => [
				'site'        => $this->makeSite( true, true ),
				'sync'        => [
					'meta'       => [
						'is_pro'       => true,
						'is_mainwp_on' => true,
						'version'      => '18.2.1',
					],
					'integrity'  => [
						'status' => 'ok',
					],
					'scan_issues' => [
						'malware' => 1,
					],
				],
				'status_key'  => ClientPluginStatus::NEED_SYNC,
			],
			'not_pro'       => [
				'site'        => $this->makeSite( true, true ),
				'sync'        => $this->canonicalSync( false, true, '18.2.1', 2, 'warning' ),
				'status_key'  => ClientPluginStatus::NOT_PRO,
			],
			'mwp_off'       => [
				'site'        => $this->makeSite( true, true ),
				'sync'        => $this->canonicalSync( true, false, '18.2.1', 2, 'warning' ),
				'status_key'  => ClientPluginStatus::MWP_NOT_ON,
			],
			'client_newer'  => [
				'site'        => $this->makeSite( true, true ),
				'sync'        => $this->canonicalSync( true, true, '18.3.0', 2, 'warning' ),
				'status_key'  => ClientPluginStatus::VERSION_NEWER_THAN_SERVER,
			],
			'client_older'  => [
				'site'        => $this->makeSite( true, true ),
				'sync'        => $this->canonicalSync( true, true, '18.1.0', 2, 'warning' ),
				'status_key'  => ClientPluginStatus::VERSION_OLDER_THAN_SERVER,
			],
			'active'        => [
				'site'        => $this->makeSite( true, true ),
				'sync'        => $this->canonicalSync( true, true, '18.2.1', 0, 'good' ),
				'status_key'  => ClientPluginStatus::ACTIVE,
			],
		];

		foreach ( $cases as $case ) {
			$status = ( new class( ( new SyncVO() )->applyFromArray( $case[ 'sync' ] ) ) extends ClientPluginStatus {
				private SyncVO $sync;

				public function __construct( SyncVO $sync ) {
					$this->sync = $sync;
				}

				protected function loadSyncData( MWPSiteVO $site ) :SyncVO {
					unset( $site );
					return $this->sync;
				}
			} )
				->setMwpSite( $case[ 'site' ] )
				->detect();

			$this->assertSame( $case[ 'status_key' ], \key( $status ) );
		}
	}

	private function makeSite( bool $isInstalled, bool $isActive ) :MWPSiteVO {
		$plugins = $isInstalled ? [
			[
				'slug'   => 'shield/shield.php',
				'active' => $isActive,
			],
		] : [];

		return ( new MWPSiteVO() )->applyFromArray( [
			'id'      => '42',
			'plugins' => \wp_json_encode( $plugins ),
		] );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function canonicalSync( bool $isPro, bool $isMainwpOn, string $version, int $total, string $severity ) :array {
		return [
			'meta'     => [
				'is_pro'       => $isPro,
				'is_mainwp_on' => $isMainwpOn,
				'version'      => $version,
			],
			'overview' => [
				'attention_summary' => [
					'total'    => $total,
					'severity' => $severity,
				],
			],
		];
	}
}
