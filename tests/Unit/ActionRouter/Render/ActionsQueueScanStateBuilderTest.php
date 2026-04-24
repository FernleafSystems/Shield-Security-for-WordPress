<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\ActionsQueueScanStateBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueScanAssetCardsBuilder,
	ScansResultsRailTabAvailability
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Counts;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory,
	UnitTestPluginUrls
};
use FernleafSystems\Wordpress\Services\Core\General;

class ActionsQueueScanStateBuilderTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
		UnitTestControllerFactory::install( new UnitTestPluginUrls() );
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_build_counts_plugin_tab_from_queue_visible_asset_summaries() :void {
		$counts = $this->getMockBuilder( Counts::class )
					   ->disableOriginalConstructor()
					   ->onlyMethods( [ 'countAffectedPluginAssets' ] )
					   ->getMock();
		$counts->method( 'countAffectedPluginAssets' )->willReturn( 4 );

		$availability = new class extends ScansResultsRailTabAvailability {
			public function build( string $tabKey ) :array {
				return $tabKey === 'plugins'
					? [
						'is_available'          => true,
						'show_in_actions_queue' => true,
						'disabled_message'      => '',
						'disabled_status'       => 'neutral',
					]
					: [
						'is_available'          => false,
						'show_in_actions_queue' => false,
						'disabled_message'      => '',
						'disabled_status'       => 'neutral',
					];
			}
		};

		$builder = new ActionsQueueScanStateBuilder();
		$this->setPrivateProperty( $builder, 'tabAvailability', $availability );
		$this->setPrivateProperty( $builder, 'displayCounts', $counts );
		$this->setPrivateProperty( $builder, 'scanAssetCardsBuilder', $this->newScanAssetCardsBuilderStub() );

		$state = $builder->build();

		$this->assertSame( 4, $state[ 'tabs' ][ 'plugins' ][ 'count' ] );
		$this->assertSame( 4, $state[ 'tabs' ][ 'summary' ][ 'count' ] );
		$this->assertSame( [ 'plugin_files' ], \array_column( $state[ 'rows' ], 'key' ) );
	}

	public function test_build_routes_fully_ignored_plugins_to_fix_now_as_warning_items() :void {
		$counts = $this->getMockBuilder( Counts::class )
					   ->disableOriginalConstructor()
					   ->onlyMethods( [ 'countAffectedPluginAssets' ] )
					   ->getMock();
		$counts->method( 'countAffectedPluginAssets' )->willReturn( 0 );

		$availability = new class extends ScansResultsRailTabAvailability {
			public function build( string $tabKey ) :array {
				return $tabKey === 'plugins'
					? [
						'is_available'          => true,
						'show_in_actions_queue' => true,
						'disabled_message'      => '',
						'disabled_status'       => 'neutral',
					]
					: [
						'is_available'          => false,
						'show_in_actions_queue' => false,
						'disabled_message'      => '',
						'disabled_status'       => 'neutral',
					];
			}
		};

		$builder = new ActionsQueueScanStateBuilder();
		$this->setPrivateProperty( $builder, 'displayCounts', $counts );
		$this->setPrivateProperty( $builder, 'tabAvailability', $availability );
		$this->setPrivateProperty( $builder, 'scanAssetCardsBuilder', $this->newScanAssetCardsBuilderStub( [
			[
				'key'          => 'ignored-plugin/ignored-plugin.php',
				'status'       => 'warning',
				'icon_class'   => 'bi bi-plug-fill',
				'title'        => 'Ignored Plugin',
				'stat_text'    => '2 discovered files are currently ignored.',
				'meta_text'    => 'ignored-plugin/ignored-plugin.php',
				'count_badge'  => 2,
				'subject_type' => 'plugin',
				'subject_id'   => 'ignored-plugin/ignored-plugin.php',
				'has_update'   => false,
			],
		] ) );

		$state = $builder->build();

		$this->assertSame( 1, $state[ 'tabs' ][ 'plugins' ][ 'count' ] );
		$this->assertSame( 'warning', $state[ 'tabs' ][ 'plugins' ][ 'status' ] );
		$this->assertSame( 1, $state[ 'tabs' ][ 'summary' ][ 'count' ] );
		$this->assertSame( 'warning', $state[ 'tabs' ][ 'summary' ][ 'status' ] );
		$this->assertSame( 'warning', $state[ 'rail_accent_status' ] );
		$this->assertSame( [ 'plugin_files_ignored' ], \array_column( $state[ 'rows' ], 'key' ) );
		$this->assertSame( 'warning', $state[ 'rows' ][ 0 ][ 'severity' ] );
		$this->assertSame( 'Review', $state[ 'rows' ][ 0 ][ 'action' ] );
		$this->assertSame( '1 plugin has discovered files currently ignored.', $state[ 'rows' ][ 0 ][ 'text' ] );
	}

	public function test_build_routes_abandoned_assets_to_separate_abandoned_tab_and_deduped_summary() :void {
		$counts = $this->getMockBuilder( Counts::class )
					   ->disableOriginalConstructor()
					   ->onlyMethods( [
						   'countDistinctVulnerableAssets',
						   'countDistinctAbandonedAssets',
						   'countDistinctVulnerabilityReviewAssets',
					   ] )
					   ->getMock();
		$counts->method( 'countDistinctVulnerableAssets' )->willReturn( 0 );
		$counts->method( 'countDistinctAbandonedAssets' )->willReturn( 2 );
		$counts->method( 'countDistinctVulnerabilityReviewAssets' )->willReturn( 2 );

		$availability = new class extends ScansResultsRailTabAvailability {
			public function build( string $tabKey ) :array {
				return \in_array( $tabKey, [ 'vulnerabilities', 'abandoned' ], true )
					? [
						'is_available'          => true,
						'show_in_actions_queue' => true,
						'disabled_message'      => '',
						'disabled_status'       => 'neutral',
					]
					: [
						'is_available'          => false,
						'show_in_actions_queue' => false,
						'disabled_message'      => '',
						'disabled_status'       => 'neutral',
					];
			}
		};

		$builder = new ActionsQueueScanStateBuilder();
		$this->setPrivateProperty( $builder, 'displayCounts', $counts );
		$this->setPrivateProperty( $builder, 'tabAvailability', $availability );

		$state = $builder->build();

		$this->assertSame( 0, $state[ 'tabs' ][ 'vulnerabilities' ][ 'count' ] );
		$this->assertSame( 'good', $state[ 'tabs' ][ 'vulnerabilities' ][ 'status' ] );
		$this->assertSame( 2, $state[ 'tabs' ][ 'abandoned' ][ 'count' ] );
		$this->assertSame( 'critical', $state[ 'tabs' ][ 'abandoned' ][ 'status' ] );
		$this->assertSame( 2, $state[ 'tabs' ][ 'summary' ][ 'count' ] );
		$this->assertSame( 'critical', $state[ 'tabs' ][ 'summary' ][ 'status' ] );
		$this->assertSame( 'critical', $state[ 'rail_accent_status' ] );
		$this->assertSame( [ 'abandoned' ], \array_column( $state[ 'rows' ], 'key' ) );
		$this->assertSame( 'critical', $state[ 'rows' ][ 0 ][ 'severity' ] );
		$this->assertSame( '/admin/scans/overview?zone=scans', $state[ 'rows' ][ 0 ][ 'href' ] );
	}

	public function test_build_keeps_summary_vulnerability_count_deduped_when_same_asset_is_vulnerable_and_abandoned() :void {
		$counts = $this->getMockBuilder( Counts::class )
					   ->disableOriginalConstructor()
					   ->onlyMethods( [
						   'countDistinctVulnerableAssets',
						   'countDistinctAbandonedAssets',
						   'countDistinctVulnerabilityReviewAssets',
					   ] )
					   ->getMock();
		$counts->method( 'countDistinctVulnerableAssets' )->willReturn( 1 );
		$counts->method( 'countDistinctAbandonedAssets' )->willReturn( 1 );
		$counts->method( 'countDistinctVulnerabilityReviewAssets' )->willReturn( 1 );

		$availability = new class extends ScansResultsRailTabAvailability {
			public function build( string $tabKey ) :array {
				return \in_array( $tabKey, [ 'vulnerabilities', 'abandoned' ], true )
					? [
						'is_available'          => true,
						'show_in_actions_queue' => true,
						'disabled_message'      => '',
						'disabled_status'       => 'neutral',
					]
					: [
						'is_available'          => false,
						'show_in_actions_queue' => false,
						'disabled_message'      => '',
						'disabled_status'       => 'neutral',
					];
			}
		};

		$builder = new ActionsQueueScanStateBuilder();
		$this->setPrivateProperty( $builder, 'displayCounts', $counts );
		$this->setPrivateProperty( $builder, 'tabAvailability', $availability );

		$state = $builder->build();

		$this->assertSame( 1, $state[ 'tabs' ][ 'vulnerabilities' ][ 'count' ] );
		$this->assertSame( 1, $state[ 'tabs' ][ 'abandoned' ][ 'count' ] );
		$this->assertSame( 1, $state[ 'tabs' ][ 'summary' ][ 'count' ] );
		$this->assertSame( [ 'vulnerable_assets', 'abandoned' ], \array_column( $state[ 'rows' ], 'key' ) );
	}

	public function test_build_surfaces_healthy_file_locker_row_without_changing_summary_count() :void {
		$availability = new class extends ScansResultsRailTabAvailability {
			public function build( string $tabKey ) :array {
				return $tabKey === 'file_locker'
					? [
						'is_available'          => true,
						'show_in_actions_queue' => true,
						'disabled_message'      => '',
						'disabled_status'       => 'neutral',
					]
					: [
						'is_available'          => false,
						'show_in_actions_queue' => false,
						'disabled_message'      => '',
						'disabled_status'       => 'neutral',
					];
			}
		};

		$builder = new class extends ActionsQueueScanStateBuilder {
			protected function getProblemFileLockerCount() :int {
				return 0;
			}

			protected function getPendingFileLockerCount() :int {
				return 0;
			}
		};
		$this->setPrivateProperty( $builder, 'tabAvailability', $availability );

		$state = $builder->build();

		$this->assertSame( 0, $state[ 'tabs' ][ 'file_locker' ][ 'count' ] );
		$this->assertSame( 'good', $state[ 'tabs' ][ 'file_locker' ][ 'status' ] );
		$this->assertSame( 0, $state[ 'tabs' ][ 'summary' ][ 'count' ] );
		$this->assertSame( 'good', $state[ 'tabs' ][ 'summary' ][ 'status' ] );
		$this->assertSame( 'good', $state[ 'rail_accent_status' ] );
		$this->assertSame( [ 'file_locker' ], \array_column( $state[ 'rows' ], 'key' ) );
		$this->assertSame( 0, $state[ 'rows' ][ 0 ][ 'count' ] );
		$this->assertSame( 'good', $state[ 'rows' ][ 0 ][ 'severity' ] );
		$this->assertSame( "Locked files don't appear to have any changes that need review.", $state[ 'rows' ][ 0 ][ 'text' ] );
	}

	public function test_build_surfaces_pending_file_locker_copy_without_changing_summary_count() :void {
		$availability = new class extends ScansResultsRailTabAvailability {
			public function build( string $tabKey ) :array {
				return $tabKey === 'file_locker'
					? [
						'is_available'          => true,
						'show_in_actions_queue' => true,
						'disabled_message'      => '',
						'disabled_status'       => 'neutral',
					]
					: [
						'is_available'          => false,
						'show_in_actions_queue' => false,
						'disabled_message'      => '',
						'disabled_status'       => 'neutral',
					];
			}
		};

		$builder = new class extends ActionsQueueScanStateBuilder {
			protected function getProblemFileLockerCount() :int {
				return 0;
			}

			protected function getPendingFileLockerCount() :int {
				return 2;
			}
		};
		$this->setPrivateProperty( $builder, 'tabAvailability', $availability );

		$state = $builder->build();

		$this->assertSame( 0, $state[ 'tabs' ][ 'file_locker' ][ 'count' ] );
		$this->assertSame( 'good', $state[ 'tabs' ][ 'file_locker' ][ 'status' ] );
		$this->assertSame( 0, $state[ 'tabs' ][ 'summary' ][ 'count' ] );
		$this->assertSame( 'good', $state[ 'tabs' ][ 'summary' ][ 'status' ] );
		$this->assertSame( 'good', $state[ 'rail_accent_status' ] );
		$this->assertSame( [ 'file_locker' ], \array_column( $state[ 'rows' ], 'key' ) );
		$this->assertSame( 0, $state[ 'rows' ][ 0 ][ 'count' ] );
		$this->assertSame( 'good', $state[ 'rows' ][ 0 ][ 'severity' ] );
		$this->assertSame( '2 initial file locks are still being created.', $state[ 'rows' ][ 0 ][ 'text' ] );
	}

	public function test_build_uses_repair_action_for_wordpress_files_on_stable_build() :void {
		$this->installWpVersion( '6.8.1' );

		$counts = $this->getMockBuilder( Counts::class )
					   ->disableOriginalConstructor()
					   ->onlyMethods( [ 'countWPFiles' ] )
					   ->getMock();
		$counts->method( 'countWPFiles' )->willReturn( 2 );

		$availability = new class extends ScansResultsRailTabAvailability {
			public function build( string $tabKey ) :array {
				return $tabKey === 'wordpress'
					? [
						'is_available'          => true,
						'show_in_actions_queue' => true,
						'disabled_message'      => '',
						'disabled_status'       => 'neutral',
					]
					: [
						'is_available'          => false,
						'show_in_actions_queue' => false,
						'disabled_message'      => '',
						'disabled_status'       => 'neutral',
					];
			}
		};

		$builder = new ActionsQueueScanStateBuilder();
		$this->setPrivateProperty( $builder, 'displayCounts', $counts );
		$this->setPrivateProperty( $builder, 'tabAvailability', $availability );

		$state = $builder->build();

		$this->assertSame( [ 'wp_files' ], \array_column( $state[ 'rows' ], 'key' ) );
		$this->assertSame( 'Repair', $state[ 'rows' ][ 0 ][ 'action' ] );
	}

	public function test_build_uses_review_action_for_wordpress_files_on_development_build() :void {
		$this->installWpVersion( '6.9-beta1' );

		$counts = $this->getMockBuilder( Counts::class )
					   ->disableOriginalConstructor()
					   ->onlyMethods( [ 'countWPFiles' ] )
					   ->getMock();
		$counts->method( 'countWPFiles' )->willReturn( 2 );

		$availability = new class extends ScansResultsRailTabAvailability {
			public function build( string $tabKey ) :array {
				return $tabKey === 'wordpress'
					? [
						'is_available'          => true,
						'show_in_actions_queue' => true,
						'disabled_message'      => '',
						'disabled_status'       => 'neutral',
					]
					: [
						'is_available'          => false,
						'show_in_actions_queue' => false,
						'disabled_message'      => '',
						'disabled_status'       => 'neutral',
					];
			}
		};

		$builder = new ActionsQueueScanStateBuilder();
		$this->setPrivateProperty( $builder, 'displayCounts', $counts );
		$this->setPrivateProperty( $builder, 'tabAvailability', $availability );

		$state = $builder->build();

		$this->assertSame( [ 'wp_files' ], \array_column( $state[ 'rows' ], 'key' ) );
		$this->assertSame( 'Review', $state[ 'rows' ][ 0 ][ 'action' ] );
	}

	private function setPrivateProperty( object $subject, string $property, $value ) :void {
		$reflection = new \ReflectionObject( $subject );
		while ( !$reflection->hasProperty( $property ) && $reflection->getParentClass() !== false ) {
			$reflection = $reflection->getParentClass();
		}

		$propertyReflection = $reflection->getProperty( $property );
		$propertyReflection->setAccessible( true );
		$propertyReflection->setValue( $subject, $value );
	}

	private function newScanAssetCardsBuilderStub( array $fullyIgnoredPluginSummaries = [] ) :ActionsQueueScanAssetCardsBuilder {
		return new class( $fullyIgnoredPluginSummaries ) extends ActionsQueueScanAssetCardsBuilder {
			/** @var array */
			private $fullyIgnoredPluginSummaries;

			public function __construct( array $fullyIgnoredPluginSummaries ) {
				$this->fullyIgnoredPluginSummaries = $fullyIgnoredPluginSummaries;
			}

			public function buildFullyIgnoredPluginSummaryRecords() :array {
				return $this->fullyIgnoredPluginSummaries;
			}
		};
	}

	private function installWpVersion( string $version ) :void {
		ServicesState::mergeItems( [
			'service_wpgeneral' => new class( $version ) extends General {
				public function __construct( private string $version ) {
				}

				public function getVersion( $ignoreClassicpress = false ) {
					return $this->version;
				}
			},
		] );
	}
}
