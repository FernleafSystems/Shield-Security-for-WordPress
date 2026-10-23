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
		$builder = new ActionsQueueScanStateBuilder();
		$this->setPrivateProperty( $builder, 'tabAvailability', $this->newAvailabilityForTabs( 'plugins' ) );
		$assetBuilder = $this->newScanAssetCardsBuilderStub( [
			'plugin' => [
				$this->assetSummary( 'plugin-a/plugin-a.php' ),
				$this->assetSummary( 'plugin-b/plugin-b.php' ),
				$this->assetSummary( 'plugin-c/plugin-c.php' ),
				$this->assetSummary( 'plugin-d/plugin-d.php' ),
			],
		] );
		$this->setPrivateProperty( $builder, 'scanAssetCardsBuilder', $assetBuilder );

		$state = $builder->build();

		$this->assertSame( 4, $state[ 'tabs' ][ 'plugins' ][ 'count' ] );
		$this->assertSame( 'critical', $state[ 'tabs' ][ 'plugins' ][ 'status' ] );
		$this->assertSame( 4, $state[ 'tabs' ][ 'summary' ][ 'count' ] );
		$this->assertSame( [ 'plugin_files' ], \array_column( $state[ 'rows' ], 'key' ) );
		$this->assertSame( 'critical', $state[ 'rows' ][ 0 ][ 'severity' ] );
		$this->assertSame( [ 'plugin' ], $assetBuilder->buildSummaryCalls );
	}

	public function test_build_ignores_fully_ignored_plugins_when_no_active_results() :void {
		$builder = new ActionsQueueScanStateBuilder();
		$this->setPrivateProperty( $builder, 'tabAvailability', $this->newAvailabilityForTabs( 'plugins' ) );
		$this->setPrivateProperty( $builder, 'scanAssetCardsBuilder', $this->newScanAssetCardsBuilderStub() );

		$state = $builder->build();

		$this->assertSame( 0, $state[ 'tabs' ][ 'plugins' ][ 'count' ] );
		$this->assertSame( 'good', $state[ 'tabs' ][ 'plugins' ][ 'status' ] );
		$this->assertSame( 0, $state[ 'tabs' ][ 'summary' ][ 'count' ] );
		$this->assertSame( 'good', $state[ 'tabs' ][ 'summary' ][ 'status' ] );
		$this->assertSame( 'good', $state[ 'rail_accent_status' ] );
		$this->assertSame( [], $state[ 'rows' ] );
	}

	public function test_build_ignores_fully_ignored_themes_when_no_active_results() :void {
		$builder = new ActionsQueueScanStateBuilder();
		$this->setPrivateProperty( $builder, 'tabAvailability', $this->newAvailabilityForTabs( 'themes' ) );
		$this->setPrivateProperty( $builder, 'scanAssetCardsBuilder', $this->newScanAssetCardsBuilderStub( [
			'theme' => [],
		] ) );

		$state = $builder->build();

		$this->assertSame( 0, $state[ 'tabs' ][ 'themes' ][ 'count' ] );
		$this->assertSame( 'good', $state[ 'tabs' ][ 'themes' ][ 'status' ] );
		$this->assertSame( 0, $state[ 'tabs' ][ 'summary' ][ 'count' ] );
		$this->assertSame( 'good', $state[ 'tabs' ][ 'summary' ][ 'status' ] );
		$this->assertSame( [], $state[ 'rows' ] );
	}

	public function test_build_treats_ignored_wordpress_results_as_clear_when_no_active_results() :void {
		$this->installWpVersion( '6.8.1' );

		$counts = $this->getMockBuilder( Counts::class )
					   ->disableOriginalConstructor()
					   ->onlyMethods( [ 'countWPFiles' ] )
					   ->getMock();
		$counts->method( 'countWPFiles' )->willReturn( 0 );

		$builder = new ActionsQueueScanStateBuilder();
		$this->setPrivateProperty( $builder, 'displayCounts', $counts );
		$this->setPrivateProperty( $builder, 'tabAvailability', $this->newAvailabilityForTabs( 'wordpress' ) );

		$state = $builder->build();

		$this->assertSame( 0, $state[ 'tabs' ][ 'wordpress' ][ 'count' ] );
		$this->assertSame( 'good', $state[ 'tabs' ][ 'wordpress' ][ 'status' ] );
		$this->assertSame( 0, $state[ 'tabs' ][ 'summary' ][ 'count' ] );
		$this->assertSame( [], $state[ 'rows' ] );
	}

	public function test_build_keeps_active_wordpress_results_critical_when_ignored_results_exist() :void {
		$this->installWpVersion( '6.8.1' );

		$counts = $this->getMockBuilder( Counts::class )
					   ->disableOriginalConstructor()
					   ->onlyMethods( [ 'countWPFiles' ] )
					   ->getMock();
		$counts->method( 'countWPFiles' )->willReturn( 1 );

		$builder = new ActionsQueueScanStateBuilder();
		$this->setPrivateProperty( $builder, 'displayCounts', $counts );
		$this->setPrivateProperty( $builder, 'tabAvailability', $this->newAvailabilityForTabs( 'wordpress' ) );

		$state = $builder->build();

		$this->assertSame( 1, $state[ 'tabs' ][ 'wordpress' ][ 'count' ] );
		$this->assertSame( 'critical', $state[ 'tabs' ][ 'wordpress' ][ 'status' ] );
		$this->assertSame( [ 'wp_files' ], \array_column( $state[ 'rows' ], 'key' ) );
		$this->assertSame( 'critical', $state[ 'rows' ][ 0 ][ 'severity' ] );
	}

	public function test_build_treats_ignored_malware_results_as_clear_when_no_active_results() :void {
		$counts = $this->getMockBuilder( Counts::class )
					   ->disableOriginalConstructor()
					   ->onlyMethods( [ 'countMalware' ] )
					   ->getMock();
		$counts->method( 'countMalware' )->willReturn( 0 );

		$builder = new ActionsQueueScanStateBuilder();
		$this->setPrivateProperty( $builder, 'displayCounts', $counts );
		$this->setPrivateProperty( $builder, 'tabAvailability', $this->newAvailabilityForTabs( 'malware' ) );

		$state = $builder->build();

		$this->assertSame( 0, $state[ 'tabs' ][ 'malware' ][ 'count' ] );
		$this->assertSame( 'good', $state[ 'tabs' ][ 'malware' ][ 'status' ] );
		$this->assertSame( 0, $state[ 'tabs' ][ 'summary' ][ 'count' ] );
		$this->assertSame( [], $state[ 'rows' ] );
	}

	public function test_build_marks_active_malware_results_critical() :void {
		$counts = $this->getMockBuilder( Counts::class )
					   ->disableOriginalConstructor()
					   ->onlyMethods( [ 'countMalware' ] )
					   ->getMock();
		$counts->method( 'countMalware' )->willReturn( 3 );

		$builder = new ActionsQueueScanStateBuilder();
		$this->setPrivateProperty( $builder, 'displayCounts', $counts );
		$this->setPrivateProperty( $builder, 'tabAvailability', $this->newAvailabilityForTabs( 'malware' ) );

		$state = $builder->build();

		$this->assertSame( 3, $state[ 'tabs' ][ 'malware' ][ 'count' ] );
		$this->assertSame( 'critical', $state[ 'tabs' ][ 'malware' ][ 'status' ] );
		$this->assertSame( [ 'malware' ], \array_column( $state[ 'rows' ], 'key' ) );
		$this->assertSame( 'critical', $state[ 'rows' ][ 0 ][ 'severity' ] );
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

		$builder = new ActionsQueueScanStateBuilder();
		$this->setPrivateProperty( $builder, 'displayCounts', $counts );
		$this->setPrivateProperty( $builder, 'tabAvailability', $this->newAvailabilityForTabs( 'vulnerabilities', 'abandoned' ) );

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

		$builder = new ActionsQueueScanStateBuilder();
		$this->setPrivateProperty( $builder, 'displayCounts', $counts );
		$this->setPrivateProperty( $builder, 'tabAvailability', $this->newAvailabilityForTabs( 'vulnerabilities', 'abandoned' ) );

		$state = $builder->build();

		$this->assertSame( 1, $state[ 'tabs' ][ 'vulnerabilities' ][ 'count' ] );
		$this->assertSame( 'critical', $state[ 'tabs' ][ 'vulnerabilities' ][ 'status' ] );
		$this->assertSame( 1, $state[ 'tabs' ][ 'abandoned' ][ 'count' ] );
		$this->assertSame( 1, $state[ 'tabs' ][ 'summary' ][ 'count' ] );
		$this->assertSame( [ 'vulnerable_assets', 'abandoned' ], \array_column( $state[ 'rows' ], 'key' ) );
		$this->assertSame( [ 'critical', 'critical' ], \array_column( $state[ 'rows' ], 'severity' ) );
	}

	public function test_build_surfaces_healthy_file_locker_row_without_changing_summary_count() :void {
		$state = $this->newFileLockerStateBuilder( 0, 0 )->build();

		$this->assertSame( 0, $state[ 'tabs' ][ 'file_locker' ][ 'count' ] );
		$this->assertSame( 'good', $state[ 'tabs' ][ 'file_locker' ][ 'status' ] );
		$this->assertSame( 0, $state[ 'tabs' ][ 'summary' ][ 'count' ] );
		$this->assertSame( 'good', $state[ 'tabs' ][ 'summary' ][ 'status' ] );
		$this->assertSame( 'good', $state[ 'rail_accent_status' ] );
		$this->assertSame( [ 'file_locker' ], \array_column( $state[ 'rows' ], 'key' ) );
		$this->assertSame( 0, $state[ 'rows' ][ 0 ][ 'count' ] );
		$this->assertSame( 'good', $state[ 'rows' ][ 0 ][ 'severity' ] );
	}

	public function test_build_surfaces_pending_file_locker_copy_without_changing_summary_count() :void {
		$state = $this->newFileLockerStateBuilder( 0, 2 )->build();

		$this->assertSame( 0, $state[ 'tabs' ][ 'file_locker' ][ 'count' ] );
		$this->assertSame( 'good', $state[ 'tabs' ][ 'file_locker' ][ 'status' ] );
		$this->assertSame( 0, $state[ 'tabs' ][ 'summary' ][ 'count' ] );
		$this->assertSame( 'good', $state[ 'tabs' ][ 'summary' ][ 'status' ] );
		$this->assertSame( 'good', $state[ 'rail_accent_status' ] );
		$this->assertSame( [ 'file_locker' ], \array_column( $state[ 'rows' ], 'key' ) );
		$this->assertSame( 0, $state[ 'rows' ][ 0 ][ 'count' ] );
		$this->assertSame( 'good', $state[ 'rows' ][ 0 ][ 'severity' ] );
	}

	public function test_build_marks_changed_file_locker_files_critical() :void {
		$state = $this->newFileLockerStateBuilder( 2, 0 )->build();

		$this->assertSame( 2, $state[ 'tabs' ][ 'file_locker' ][ 'count' ] );
		$this->assertSame( 'critical', $state[ 'tabs' ][ 'file_locker' ][ 'status' ] );
		$this->assertSame( 'critical', $state[ 'rail_accent_status' ] );
		$this->assertSame( [ 'file_locker' ], \array_column( $state[ 'rows' ], 'key' ) );
		$this->assertSame( 2, $state[ 'rows' ][ 0 ][ 'count' ] );
		$this->assertSame( 'critical', $state[ 'rows' ][ 0 ][ 'severity' ] );
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

	private function newScanAssetCardsBuilderStub( array $activeSummaries = [] ) :ActionsQueueScanAssetCardsBuilder {
		if ( !isset( $activeSummaries[ 'plugin' ] ) && !isset( $activeSummaries[ 'theme' ] ) ) {
			$activeSummaries = [
				'plugin' => $activeSummaries,
			];
		}

		return new class( $activeSummaries ) extends ActionsQueueScanAssetCardsBuilder {
			/** @var array<string,array> */
			private $activeSummaries;

			public array $buildSummaryCalls = [];

			public function __construct( array $activeSummaries ) {
				$this->activeSummaries = $activeSummaries;
			}

			public function buildSummaryRecords( string $assetType, array $resultsDisplayOptions = [] ) :array {
				$this->buildSummaryCalls[] = $assetType;
				return $this->activeSummaries[ $assetType ] ?? [];
			}
		};
	}

	private function assetSummary( string $key, int $count = 1, string $assetType = 'plugin' ) :array {
		return [
			'key'          => $key,
			'status'       => 'warning',
			'icon_class'   => $assetType === 'plugin' ? 'bi bi-plug-fill' : 'bi bi-palette-fill',
			'title'        => $key,
			'stat_text'    => 'needs review',
			'meta_text'    => $key,
			'count_badge'  => $count,
			'subject_type' => $assetType,
			'subject_id'   => $key,
			'has_update'   => false,
		];
	}

	private function newFileLockerStateBuilder( int $problemCount, int $pendingCount ) :ActionsQueueScanStateBuilder {
		$builder = new class( $problemCount, $pendingCount ) extends ActionsQueueScanStateBuilder {
			private int $problemCount;
			private int $pendingCount;

			public function __construct( int $problemCount, int $pendingCount ) {
				$this->problemCount = $problemCount;
				$this->pendingCount = $pendingCount;
			}

			protected function getProblemFileLockerCount() :int {
				return $this->problemCount;
			}

			protected function getPendingFileLockerCount() :int {
				return $this->pendingCount;
			}
		};
		$this->setPrivateProperty( $builder, 'tabAvailability', $this->newAvailabilityForTabs( 'file_locker' ) );

		return $builder;
	}

	private function newAvailabilityForTabs( string ...$availableTabKeys ) :ScansResultsRailTabAvailability {
		return new class( $availableTabKeys ) extends ScansResultsRailTabAvailability {
			/** @var list<string> */
			private array $availableTabKeys;

			/**
			 * @param list<string> $availableTabKeys
			 */
			public function __construct( array $availableTabKeys ) {
				$this->availableTabKeys = $availableTabKeys;
			}

			public function build( string $tabKey ) :array {
				return \in_array( $tabKey, $this->availableTabKeys, true )
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
	}

	private function installWpVersion( string $version ) :void {
		ServicesState::mergeItems( [
			'service_wpgeneral' => new class( $version ) extends General {
				private string $version;

				public function __construct( string $version ) {
					$this->version = $version;
				}

				public function getVersion( $ignoreClassicpress = false ) {
					return $this->version;
				}
			},
		] );
	}
}
