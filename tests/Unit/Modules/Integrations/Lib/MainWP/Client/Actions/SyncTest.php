<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Integrations\Lib\MainWP\Client\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client\Actions\Sync;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Services\Core\Plugins;

class SyncTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_request'   => new UnitTestRequest( [], '127.0.0.1', 1700000000 ),
			'service_wpplugins' => new class extends Plugins {
				public function isUpdateAvailable( $file ) :bool {
					return $file === 'shield/shield.php';
				}
			},
		] );

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
				'caps'      => new class {
					public function canMainwpLevel1() :bool {
						return true;
					}
				},
				'comps'     => (object)[
					'license'    => new class {
						public function hasValidWorkingLicense() :bool {
							return true;
						}
					},
					'opts_lookup' => new class {
						public function enabledIntegrationMainwp() :bool {
							return true;
						}

						public function getInstalledAt() :int {
							return 1234;
						}
					},
				],
			]
		);
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_run_returns_overview_and_drops_legacy_integrity_fields() :void {
		$result = ( new class extends Sync {
			protected function buildOverviewQuery() :array {
				return [
					'attention_summary' => [
						'total'        => 4,
						'severity'     => 'warning',
						'is_all_clear' => false,
					],
					'posture'           => [
						'severity'   => 'warning',
						'percentage' => 75,
						'controls'   => [ 'total' => 4, 'good' => 2, 'warning' => 1, 'critical' => 1 ],
						'zones'      => [ 'total' => 3, 'good' => 1, 'warning' => 1, 'critical' => 1 ],
					],
					'scans'             => [
						'is_running'         => false,
						'enqueued_count'     => 0,
						'latest_completed_at' => [],
					],
				];
			}
		} )->run();

		$this->assertSame( 1700000000, $result[ 'meta' ][ 'sync_at' ] );
		$this->assertTrue( $result[ 'meta' ][ 'is_pro' ] );
		$this->assertTrue( $result[ 'meta' ][ 'is_mainwp_on' ] );
		$this->assertTrue( $result[ 'meta' ][ 'has_update' ] );
		$this->assertSame( 4, $result[ 'overview' ][ 'attention_summary' ][ 'total' ] );
		$this->assertArrayNotHasKey( 'integrity', $result );
		$this->assertArrayNotHasKey( 'scan_issues', $result );
	}
}
