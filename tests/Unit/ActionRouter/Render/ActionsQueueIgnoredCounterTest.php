<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\ActionsQueueIgnoredCounter;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	CloakedPluginsQueueIssueProvider,
	ScansVulnerabilitiesBuilder
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory
};

class ActionsQueueIgnoredCounterTest extends BaseUnitTest {

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_to_dos_counts_every_ignored_item_of_every_kind() :void {
		$counter = new ActionsQueueIgnoredCounterTestDouble( [
			'wp_db_password'      => [ 'ignored_count' => 1 ],
			'wp_plugins_inactive' => [ 'ignored_count' => 3 ],
			'system_php_version'  => [ 'ignored_count' => 0 ],
		] );

		$this->assertSame( 4, $counter->toDos() );
	}

	/**
	 * @dataProvider securityIssuesProvider
	 */
	public function test_security_issues_counts_only_what_the_queue_reports( bool $abandonedScanOn, bool $canDetectCloaked, int $expected ) :void {
		$this->installController( $abandonedScanOn, $canDetectCloaked );

		// Two abandoned plugins ignored, and one cloaked plugin.
		$this->assertSame( $expected, ( new ActionsQueueIgnoredCounterTestDouble( [], 2, 1 ) )->securityIssues() );
	}

	public function securityIssuesProvider() :array {
		return [
			'both reported'                  => [ true, true, 3 ],
			'abandoned scan turned off'      => [ false, true, 1 ],
			'cloaked plugins not detectable' => [ true, false, 2 ],
			'neither reported'               => [ false, false, 0 ],
		];
	}

	private function installController( bool $abandonedScanOn, bool $canDetectCloaked ) :void {
		UnitTestControllerFactory::install( null, null, (object)[
			'comps' => (object)[
				'scans' => new class( $abandonedScanOn ) {
					private bool $abandonedScanOn;

					public function __construct( bool $abandonedScanOn ) {
						$this->abandonedScanOn = $abandonedScanOn;
					}

					public function APC() :object {
						return new class( $this->abandonedScanOn ) {
							private bool $enabled;

							public function __construct( bool $enabled ) {
								$this->enabled = $enabled;
							}

							public function isEnabled() :bool {
								return $this->enabled;
							}
						};
					}
				},
			],
			'caps'  => new class( $canDetectCloaked ) {
				private bool $canDetectCloaked;

				public function __construct( bool $canDetectCloaked ) {
					$this->canDetectCloaked = $canDetectCloaked;
				}

				public function canDetectCloakedPlugins() :bool {
					return $this->canDetectCloaked;
				}
			},
		] );
	}
}

class ActionsQueueIgnoredCounterTestDouble extends ActionsQueueIgnoredCounter {

	private array $maintenanceStates;
	private int $ignoredAbandoned;
	private int $ignoredCloaked;

	public function __construct( array $maintenanceStates, int $ignoredAbandoned = 0, int $ignoredCloaked = 0 ) {
		$this->maintenanceStates = $maintenanceStates;
		$this->ignoredAbandoned = $ignoredAbandoned;
		$this->ignoredCloaked = $ignoredCloaked;
	}

	protected function buildMaintenanceStates() :array {
		return $this->maintenanceStates;
	}

	protected function buildVulnerabilitiesBuilder() :ScansVulnerabilitiesBuilder {
		return new class( $this->ignoredAbandoned ) extends ScansVulnerabilitiesBuilder {
			private int $ignoredAbandoned;

			public function __construct( int $ignoredAbandoned ) {
				$this->ignoredAbandoned = $ignoredAbandoned;
			}

			public function buildIgnoredAbandonedItems( string $assetType = '' ) :array {
				return \array_fill( 0, $this->ignoredAbandoned, [] );
			}
		};
	}

	protected function buildCloakedPluginsProvider() :CloakedPluginsQueueIssueProvider {
		return new class( $this->ignoredCloaked ) extends CloakedPluginsQueueIssueProvider {
			private int $ignoredCloaked;

			public function __construct( int $ignoredCloaked ) {
				$this->ignoredCloaked = $ignoredCloaked;
			}

			public function ignoredByUserCount() :int {
				return $this->ignoredCloaked;
			}
		};
	}
}
