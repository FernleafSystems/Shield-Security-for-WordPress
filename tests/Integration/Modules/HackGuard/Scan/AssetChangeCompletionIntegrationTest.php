<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\HackGuard\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\SetScanCompleted;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\QueueItemVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\{
	BuildAlertDigestContract,
	Constants,
	ReportVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data\BuildForScans;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ScanActionVO;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\HackGuard\Scan\Support\AfsAssetChangeIntegrationSupport;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class AssetChangeCompletionIntegrationTest extends ShieldIntegrationTestCase {

	use AfsAssetChangeIntegrationSupport;

	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_items' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );

		$this->loginAsSecurityAdmin();
		$this->enablePremiumCapabilities( [
			'scan_file_areas',
			'scan_pluginsthemes_local',
		] );
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'enable_core_file_integrity_scan',
			'file_scan_areas',
		] );

		self::con()->opts
			->optSet( 'enable_core_file_integrity_scan', 'Y' )
			->optSet( 'file_scan_areas', [ 'wp', 'plugins', 'themes' ] )
			->store();
		self::con()->cache_dir_handler->buildSubDir( 'integration-fixture' );
	}

	public function tear_down() {
		if ( static::con() !== null ) {
			$this->restoreSelectedOptions( $this->optionsSnapshot );
		}
		parent::tear_down();
	}

	public function test_asset_change_completion_invalidates_warm_scan_result_count_cache() :void {
		$scenario = $this->afsAssetScenario( 'plugin' );
		$initialScanId = TestDataFactory::insertCompletedScan( 'afs' );
		$this->seedAfsFinding( $initialScanId, $scenario, $scenario[ 'path_full' ] );
		$stale = $this->seedAfsFinding( $initialScanId, $scenario, $scenario[ 'stale_path_full' ] );

		$this->resetScanResultCountMemoization();
		$this->assertSame( 2, self::con()->comps->scans->getScanResultsCount()->countPluginFiles() );

		$replacementScanId = $this->insertAfsScan( $scenario[ 'scope_type' ], $scenario[ 'scope_key' ], [
			ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY,
		] );
		$this->storeAfsObservation( $replacementScanId, $scenario );
		$this->assertTrue( ( new SetScanCompleted() )->run( $replacementScanId ) );
		$staleItem = self::con()->db_con->scan_result_items->getQuerySelector()
			->byId( (int)$stale[ 'result_item_id' ] );

		$this->assertSame( 'asset_replaced', (string)$staleItem->resolution_reason );
		$this->assertSame( 1, self::con()->comps->scans->getScanResultsCount()->countPluginFiles() );
	}

	public function test_root_plugin_results_persist_exact_owners_and_complete_only_matching_scope() :void {
		$first = [
			'path_full'  => \wp_normalize_path( WP_PLUGIN_DIR.'/first.php' ),
			'meta'       => [
				'is_in_plugin'    => 1,
				'is_checksumfail' => 1,
				'ptg_slug'        => 'first.php',
			],
		];
		$second = [
			'path_full'  => \wp_normalize_path( WP_PLUGIN_DIR.'/second.php' ),
			'meta'       => [
				'is_in_plugin'    => 1,
				'is_checksumfail' => 1,
				'ptg_slug'        => 'second.php',
			],
		];
		$initialScanID = $this->insertAfsScan( 'full', '', [
			ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY,
		], 'manual' );
		$firstItem = $this->storeAfsObservation( $initialScanID, $first );
		$secondItem = $this->storeAfsObservation( $initialScanID, $second );
		$this->assertSame( 'plugin', (string)$firstItem->asset_type );
		$this->assertSame( 'first.php', (string)$firstItem->asset_key );
		$this->assertSame( 'plugin', (string)$secondItem->asset_type );
		$this->assertSame( 'second.php', (string)$secondItem->asset_key );

		$replacementScanID = $this->insertAfsScan( 'plugin', 'first.php', [
			ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY,
		] );
		$this->assertTrue( ( new SetScanCompleted() )->run( $replacementScanID ) );

		$firstItem = self::con()->db_con->scan_result_items->getQuerySelector()->byId( (int)$firstItem->id );
		$secondItem = self::con()->db_con->scan_result_items->getQuerySelector()->byId( (int)$secondItem->id );
		$this->assertGreaterThan( 0, (int)$firstItem->resolved_at );
		$this->assertSame( 'asset_replaced', (string)$firstItem->resolution_reason );
		$this->assertSame( 0, (int)$secondItem->resolved_at );
		$this->assertSame( '', (string)$secondItem->resolution_reason );
	}

	public function test_asset_change_completion_invalidates_warm_afs_display_results_cache() :void {
		$scenario = $this->afsAssetScenario( 'plugin' );
		$initialScanId = TestDataFactory::insertCompletedScan( 'afs' );
		$this->seedAfsFinding( $initialScanId, $scenario, $scenario[ 'path_full' ] );
		$this->seedAfsFinding( $initialScanId, $scenario, $scenario[ 'stale_path_full' ] );
		$stalePathFragment = TestDataFactory::afsFileItemIdFromPath( $scenario[ 'stale_path_full' ] );

		$this->resetScanResultCountMemoization();
		$this->assertSame( 2, self::con()->comps->scans->AFS()->getResultsForDisplay()->countItems() );

		$replacementScanId = $this->insertAfsScan( $scenario[ 'scope_type' ], $scenario[ 'scope_key' ], [
			ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY,
		] );
		$this->storeAfsObservation( $replacementScanId, $scenario );
		$this->assertTrue( ( new SetScanCompleted() )->run( $replacementScanId ) );

		$report = $this->buildAlertReport();
		$report->areas_data = [
			Constants::REPORT_AREA_SCANS => ( new BuildForScans( $report ) )->build(),
		];
		$report->alert_digest = ( new BuildAlertDigestContract() )->build( $report );
		$scanRow = $this->scanResultsRow( $report, $scenario[ 'digest_slug' ] );

		$this->assertSame( 1, $scanRow[ 'count' ] );
		$this->assertNotContains( $stalePathFragment, \array_column( $scanRow[ 'items' ], 'label' ) );
		foreach ( $report->alert_digest[ 'rows' ] as $digestRow ) {
			$this->assertNotContains( $stalePathFragment, \array_column( $digestRow[ 'new_items' ], 'label' ) );
			$this->assertNotContains( $stalePathFragment, \array_column( $digestRow[ 'outstanding_items' ], 'label' ) );
		}
	}

	public function test_core_asset_change_completion_resolves_only_modified_or_missing_core_rows() :void {
		$core = $this->afsAssetScenario( 'core' );
		$initialScanId = TestDataFactory::insertCompletedScan( 'afs' );
		$checksum = $this->seedAfsFinding( $initialScanId, $core, \path_join( ABSPATH, WPINC.'/asset-checksum.php' ), [
			'is_in_core'      => 1,
			'is_checksumfail' => 1,
		] );
		$missing = $this->seedAfsFinding( $initialScanId, $core, \path_join( ABSPATH, WPINC.'/asset-missing.php' ), [
			'is_in_core'  => 1,
			'is_missing'  => 1,
		] );
		$unrecognised = $this->seedAfsFinding( $initialScanId, $core, \path_join( ABSPATH, WPINC.'/asset-unrecognised.php' ), [
			'is_in_core'       => 1,
			'is_unrecognised'  => 1,
		] );
		$malware = $this->seedAfsFinding( $initialScanId, $core, \path_join( ABSPATH, WPINC.'/asset-malware.php' ), [
			'is_in_core' => 1,
			'is_mal'     => 1,
		] );

		$replacementScanId = $this->insertAfsScan( 'core', 'core', [
			ScanActionVO::COVERAGE_FAMILY_CORE_INTEGRITY,
		] );
		$this->assertTrue( ( new SetScanCompleted() )->run( $replacementScanId ) );

		foreach ( [ $checksum, $missing ] as $resolved ) {
			$item = self::con()->db_con->scan_result_items->getQuerySelector()
				->byId( (int)$resolved[ 'result_item_id' ] );
			$this->assertGreaterThan( 0, (int)$item->resolved_at );
			$this->assertSame( 'asset_replaced', (string)$item->resolution_reason );
		}
		foreach ( [ $unrecognised, $malware ] as $active ) {
			$item = self::con()->db_con->scan_result_items->getQuerySelector()
				->byId( (int)$active[ 'result_item_id' ] );
			$this->assertSame( 0, (int)$item->resolved_at );
			$this->assertSame( '', (string)$item->resolution_reason );
		}
	}

	public function test_full_completion_resolves_only_enabled_coverage_families() :void {
		$plugin = $this->afsAssetScenario( 'plugin' );
		$core = $this->afsAssetScenario( 'core' );
		$initialScanID = TestDataFactory::insertCompletedScan( 'afs' );
		$covered = $this->seedAfsFinding( $initialScanID, $core, $this->findingPath( 'covered-core' ), [
			'is_in_core'      => 1,
			'is_checksumfail' => 1,
		] );
		$notCovered = $this->seedAfsFinding( $initialScanID, $plugin, $this->findingPath( 'uncovered-plugin' ), [
			'is_in_plugin'    => 1,
			'is_checksumfail' => 1,
			'ptg_slug'        => $plugin[ 'asset_key' ],
		] );

		$scanID = $this->insertAfsScan( 'full', '', [
			ScanActionVO::COVERAGE_FAMILY_CORE_INTEGRITY,
		], 'manual' );
		$this->assertTrue( ( new SetScanCompleted() )->run( $scanID ) );

		$this->assertFindingResolved( $covered, true, 'clean_rescan' );
		$this->assertFindingResolved( $notCovered, false );
	}

	/**
	 * @dataProvider provideCoveredIssueFamilies
	 * @param array<string,int|string> $meta
	 */
	public function test_full_completion_resolves_each_covered_issue_family(
		string $case,
		array $meta,
		string $coverageFamily
	) :void {
		$scenario = $this->afsAssetScenario( 'plugin' );
		$assetType = null;
		if ( $coverageFamily === ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY ) {
			$assetType = 'plugin';
		}
		elseif ( $coverageFamily === ScanActionVO::COVERAGE_FAMILY_THEME_INTEGRITY ) {
			$assetType = 'theme';
		}
		if ( $assetType !== null ) {
			$meta[ 'asset_version' ] = '1.0';
		}
		$initialScanID = TestDataFactory::insertCompletedScan( 'afs' );
		$finding = $this->seedAfsFinding(
			$initialScanID,
			$scenario,
			$this->findingPath( 'covered-'.$case ),
			$meta
		);

		$scanID = $this->insertAfsScan( 'full', '', [ $coverageFamily ], 'manual' );
		if ( $assetType !== null ) {
			$this->setFullScanEligibility( $scanID, [
				$assetType => [
					(string)$meta[ 'ptg_slug' ] => [
						'version'             => '1.0',
						'comparison_eligible' => true,
					],
				],
			] );
		}
		$this->assertTrue( ( new SetScanCompleted() )->run( $scanID ) );

		$this->assertFindingResolved( $finding, true, 'clean_rescan' );
	}

	public function provideCoveredIssueFamilies() :array {
		return [
			'unrecognised' => [
				'unrecognised',
				[
					'is_in_core'      => '',
					'is_in_plugin'    => 1,
					'is_in_theme'     => 0,
					'is_unrecognised' => 1,
					'ptg_slug'        => 'coverage-plugin',
				],
				ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY,
			],
			'malware' => [
				'malware',
				[ 'is_mal' => 1 ],
				ScanActionVO::COVERAGE_FAMILY_MALWARE,
			],
			'missing' => [
				'missing',
				[ 'is_in_core' => 1, 'is_missing' => 1 ],
				ScanActionVO::COVERAGE_FAMILY_CORE_INTEGRITY,
			],
			'checksum failure' => [
				'checksum',
				[ 'is_in_theme' => 1, 'is_checksumfail' => 1, 'ptg_slug' => 'coverage-theme' ],
				ScanActionVO::COVERAGE_FAMILY_THEME_INTEGRITY,
			],
			'unidentified' => [
				'unidentified',
				[ 'is_in_core' => 1, 'is_in_wproot' => 1, 'is_unidentified' => 1 ],
				ScanActionVO::COVERAGE_FAMILY_WPROOT_UNIDENTIFIED,
			],
		];
	}

	/**
	 * @dataProvider provideIssuePrecedence
	 * @param array<string,int|string> $meta
	 */
	public function test_issue_precedence_uses_highest_issue_family(
		string $case,
		array $meta,
		string $higherCoverage,
		string $lowerCoverage
	) :void {
		$plugin = $this->afsAssetScenario( 'plugin' );
		$assetType = null;
		if ( $higherCoverage === ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY ) {
			$assetType = 'plugin';
		}
		elseif ( $higherCoverage === ScanActionVO::COVERAGE_FAMILY_THEME_INTEGRITY ) {
			$assetType = 'theme';
		}
		if ( $assetType !== null ) {
			$meta[ 'asset_version' ] = '1.0';
		}
		$initialScanID = TestDataFactory::insertCompletedScan( 'afs' );
		$finding = $this->seedAfsFinding(
			$initialScanID,
			$plugin,
			$this->findingPath( 'precedence-'.$case ),
			$meta
		);

		$lowerScanID = $this->insertAfsScan( 'full', '', [ $lowerCoverage ], 'manual' );
		$this->assertTrue( ( new SetScanCompleted() )->run( $lowerScanID ) );
		$this->assertFindingResolved( $finding, false );

		$higherScanID = $this->insertAfsScan( 'full', '', [ $higherCoverage ], 'manual' );
		if ( $assetType !== null ) {
			$this->setFullScanEligibility( $higherScanID, [
				$assetType => [
					(string)$meta[ 'ptg_slug' ] => [
						'version'             => '1.0',
						'comparison_eligible' => true,
					],
				],
			] );
		}
		$this->assertTrue( ( new SetScanCompleted() )->run( $higherScanID ) );
		$this->assertFindingResolved( $finding, true, 'clean_rescan' );
	}

	public function provideIssuePrecedence() :array {
		return [
			'unrecognised before malware' => [
				'unrecognised-malware',
				[
					'is_in_plugin'    => 1,
					'is_unrecognised' => 1,
					'is_mal'          => 1,
					'ptg_slug'        => 'precedence-plugin',
				],
				ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY,
				ScanActionVO::COVERAGE_FAMILY_MALWARE,
			],
			'missing before unidentified' => [
				'missing-unidentified',
				[ 'is_in_core' => 1, 'is_in_wproot' => 1, 'is_missing' => 1, 'is_unidentified' => 1 ],
				ScanActionVO::COVERAGE_FAMILY_CORE_INTEGRITY,
				ScanActionVO::COVERAGE_FAMILY_WPROOT_UNIDENTIFIED,
			],
			'checksum failure before unidentified' => [
				'checksum-unidentified',
				[
					'is_in_theme'     => 1,
					'is_in_wproot'    => 1,
					'is_checksumfail' => 1,
					'is_unidentified' => 1,
					'ptg_slug'        => 'precedence-theme',
				],
				ScanActionVO::COVERAGE_FAMILY_THEME_INTEGRITY,
				ScanActionVO::COVERAGE_FAMILY_WPROOT_UNIDENTIFIED,
			],
		];
	}

	public function test_ambiguous_integrity_ownership_and_unidentified_location_fail_closed() :void {
		$scenario = $this->afsAssetScenario( 'plugin' );
		$initialScanID = TestDataFactory::insertCompletedScan( 'afs' );
		$ambiguousOwnership = $this->seedAfsFinding(
			$initialScanID,
			$scenario,
			$this->findingPath( 'ambiguous-ownership' ),
			[
				'is_in_plugin'    => 1,
				'is_in_theme'     => 1,
				'is_checksumfail' => 1,
				'ptg_slug'        => 'ambiguous-asset',
			]
		);
		$ambiguousLocation = $this->seedAfsFinding(
			$initialScanID,
			$scenario,
			$this->findingPath( 'ambiguous-location' ),
			[
				'is_in_core'      => 1,
				'is_in_wproot'    => 1,
				'is_in_wpcontent' => 1,
				'is_unidentified' => 1,
			]
		);
		$ambiguousCoreOwnership = $this->seedAfsFinding(
			$initialScanID,
			$scenario,
			$this->findingPath( 'ambiguous-core-ownership' ),
			[
				'is_in_core'      => 1,
				'is_in_plugin'    => 1,
				'is_checksumfail' => 1,
				'ptg_slug'        => 'ambiguous-core-plugin.php',
				'asset_version'   => '1.0',
			]
		);

		$scanID = $this->insertAfsScan( 'full', '', [
			ScanActionVO::COVERAGE_FAMILY_CORE_INTEGRITY,
			ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY,
			ScanActionVO::COVERAGE_FAMILY_THEME_INTEGRITY,
			ScanActionVO::COVERAGE_FAMILY_WPROOT_UNIDENTIFIED,
			ScanActionVO::COVERAGE_FAMILY_WPCONTENT_UNIDENTIFIED,
		], 'manual' );
		$this->assertTrue( ( new SetScanCompleted() )->run( $scanID ) );

		$this->assertFindingResolved( $ambiguousOwnership, false );
		$this->assertFindingResolved( $ambiguousLocation, false );
		$this->assertFindingResolved( $ambiguousCoreOwnership, false );
	}

	public function test_full_completion_resolves_only_exact_eligible_asset_versions() :void {
		$plugin = $this->afsAssetScenario( 'plugin' );
		$theme = $this->afsAssetScenario( 'theme' );
		$initialScanID = TestDataFactory::insertCompletedScan( 'afs' );
		$eligiblePlugin = $this->seedAfsFinding(
			$initialScanID,
			$plugin,
			$this->findingPath( 'eligible-plugin' ),
			[
				'is_in_plugin'    => 1,
				'is_checksumfail' => 1,
				'ptg_slug'        => 'eligible/plugin.php',
				'asset_version'   => '1.0',
			]
		);
		$falsePlugin = $this->seedAfsFinding(
			$initialScanID,
			$plugin,
			$this->findingPath( 'false-plugin' ),
			[
				'is_in_plugin'    => 1,
				'is_checksumfail' => 1,
				'ptg_slug'        => 'false/plugin.php',
				'asset_version'   => '1.0',
			]
		);
		$wrongVersion = $this->seedAfsFinding(
			$initialScanID,
			$plugin,
			$this->findingPath( 'wrong-version' ),
			[
				'is_in_plugin'    => 1,
				'is_checksumfail' => 1,
				'ptg_slug'        => 'version/plugin.php',
				'asset_version'   => '2.0',
			]
		);
		$missingKey = $this->seedAfsFinding(
			$initialScanID,
			$plugin,
			$this->findingPath( 'missing-key' ),
			[
				'is_in_plugin'    => 1,
				'is_checksumfail' => 1,
				'ptg_slug'        => 'missing/plugin.php',
				'asset_version'   => '1.0',
			]
		);
		$numericTheme = $this->seedAfsFinding(
			$initialScanID,
			$theme,
			$this->findingPath( 'numeric-theme-zero-version' ),
			[
				'is_in_theme'     => 1,
				'is_checksumfail' => 1,
				'ptg_slug'        => '7',
				'asset_version'   => '0',
			]
		);

		$scanID = $this->insertAfsScan( 'full', '', [
			ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY,
			ScanActionVO::COVERAGE_FAMILY_THEME_INTEGRITY,
		], 'manual' );
		$this->setFullScanEligibility( $scanID, [
			'plugin' => [
				'eligible/plugin.php' => [
					'version'             => '1.0',
					'comparison_eligible' => true,
				],
				'false/plugin.php'    => [
					'version'             => '1.0',
					'comparison_eligible' => false,
				],
				'version/plugin.php'  => [
					'version'             => '1.0',
					'comparison_eligible' => true,
				],
			],
			'theme'  => [
				7 => [
					'version'             => '0',
					'comparison_eligible' => true,
				],
			],
		] );

		$this->assertTrue( ( new SetScanCompleted() )->run( $scanID ) );
		$this->assertFindingResolved( $eligiblePlugin, true, 'clean_rescan' );
		$this->assertFindingResolved( $numericTheme, true, 'clean_rescan' );
		foreach ( [ $falsePlugin, $wrongVersion, $missingKey ] as $active ) {
			$this->assertFindingResolved( $active, false );
		}
	}

	public function test_full_completion_excludes_marked_asset_but_reconciles_siblings_and_malware() :void {
		$plugin = $this->afsAssetScenario( 'plugin' );
		$initialScanID = TestDataFactory::insertCompletedScan( 'afs' );
		$affected = $this->seedAfsFinding(
			$initialScanID,
			$plugin,
			$this->findingPath( 'comparison-incomplete-plugin' ),
			[
				'is_in_plugin'    => 1,
				'is_checksumfail' => 1,
				'is_mal'          => 1,
				'ptg_slug'        => 'affected/plugin.php',
				'asset_version'   => '1.0',
			]
		);
		$unrelated = $this->seedAfsFinding(
			$initialScanID,
			$plugin,
			$this->findingPath( 'comparison-complete-plugin' ),
			[
				'is_in_plugin'    => 1,
				'is_checksumfail' => 1,
				'ptg_slug'        => 'unrelated/plugin.php',
				'asset_version'   => '2.0',
			]
		);
		$pureMalware = $this->seedAfsFinding(
			$initialScanID,
			$plugin,
			$this->findingPath( 'comparison-incomplete-pure-malware' ),
			[ 'is_mal' => 1 ]
		);

		$scanID = $this->insertAfsScan( 'full', '', [
			ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY,
			ScanActionVO::COVERAGE_FAMILY_MALWARE,
		], 'manual' );
		$this->setFullScanEligibility( $scanID, [
			'plugin' => [
				'affected/plugin.php'  => [
					'version'             => '1.0',
					'comparison_eligible' => true,
				],
				'unrelated/plugin.php' => [
					'version'             => '2.0',
					'comparison_eligible' => true,
				],
			],
		] );
		$record = self::con()->db_con->scans->getQuerySelector()->byId( $scanID );
		$meta = $record->meta;
		$meta[ 'asset_comparison_incomplete' ] = [
			'plugin' => [ 'affected/plugin.php' ],
			'theme'  => [],
		];
		$this->replaceScanMeta( $scanID, $meta );

		$this->assertTrue( ( new SetScanCompleted() )->run( $scanID ) );
		$this->assertFindingResolved( $affected, false );
		$this->assertSame( '0', $this->resultItemMetaValue( (int)$affected[ 'result_item_id' ], 'is_mal' ) );
		$this->assertSame( '1', $this->resultItemMetaValue( (int)$affected[ 'result_item_id' ], 'is_checksumfail' ) );
		$this->assertFindingResolved( $unrelated, true, 'clean_rescan' );
		$this->assertFindingResolved( $pureMalware, true, 'clean_rescan' );
	}

	public function test_full_completion_accepts_valid_empty_eligibility_without_resolving_assets() :void {
		$plugin = $this->afsAssetScenario( 'plugin' );
		$initialScanID = TestDataFactory::insertCompletedScan( 'afs' );
		$finding = $this->seedAfsFinding(
			$initialScanID,
			$plugin,
			$this->findingPath( 'valid-empty-eligibility' ),
			[
				'is_in_plugin'    => 1,
				'is_checksumfail' => 1,
				'ptg_slug'        => 'empty/plugin.php',
				'asset_version'   => '1.0',
			]
		);
		$scanID = $this->insertAfsScan( 'full', '', [
			ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY,
		], 'manual' );
		$this->setFullScanEligibility( $scanID, [] );

		$this->assertTrue( ( new SetScanCompleted() )->run( $scanID ) );
		$this->assertFindingResolved( $finding, false );
	}

	/**
	 * @dataProvider invalidAssetEligibilityProvider
	 * @param array<string,mixed>|null $eligibility
	 */
	public function test_full_completion_fails_closed_for_invalid_asset_eligibility( ?array $eligibility ) :void {
		$plugin = $this->afsAssetScenario( 'plugin' );
		$initialScanID = TestDataFactory::insertCompletedScan( 'afs' );
		$finding = $this->seedAfsFinding(
			$initialScanID,
			$plugin,
			$this->findingPath( 'invalid-eligibility' ),
			[
				'is_in_plugin'    => 1,
				'is_checksumfail' => 1,
				'ptg_slug'        => 'invalid/plugin.php',
				'asset_version'   => '1.0',
			]
		);
		$scanID = $this->insertAfsScan( 'full', '', [
			ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY,
		], 'manual' );
		if ( $eligibility !== null ) {
			$record = self::con()->db_con->scans->getQuerySelector()->byId( $scanID );
			$meta = $record->meta;
			$meta[ 'asset_snapshot_eligibility' ] = $eligibility;
			$this->replaceScanMeta( $scanID, $meta );
		}

		$this->assertTrue( ( new SetScanCompleted() )->run( $scanID ) );
		$this->assertFindingResolved( $finding, false );
	}

	public function invalidAssetEligibilityProvider() :array {
		return [
			'missing' => [ null ],
			'missing theme group' => [
				[
					'plugin' => [
						'invalid/plugin.php' => [
							'version'             => '1.0',
							'comparison_eligible' => true,
						],
					],
				],
			],
			'malformed entry' => [
				[
					'plugin' => [
						'invalid/plugin.php' => [
							'comparison_eligible' => true,
						],
					],
					'theme'  => [],
				],
			],
		];
	}

	public function test_full_malware_completion_is_independent_of_invalid_asset_eligibility() :void {
		$plugin = $this->afsAssetScenario( 'plugin' );
		$core = $this->afsAssetScenario( 'core' );
		$initialScanID = TestDataFactory::insertCompletedScan( 'afs' );
		$mixed = $this->seedAfsFinding(
			$initialScanID,
			$plugin,
			$this->findingPath( 'mixed-malware' ),
			[
				'is_in_plugin'    => 1,
				'is_checksumfail' => 1,
				'is_mal'          => 1,
				'ptg_slug'        => 'mixed/plugin.php',
				'asset_version'   => '1.0',
			]
		);
		$pureMalware = $this->seedAfsFinding(
			$initialScanID,
			$plugin,
			$this->findingPath( 'pure-malware' ),
			[ 'is_mal' => 1 ]
		);
		$coreFinding = $this->seedAfsFinding(
			$initialScanID,
			$core,
			$this->findingPath( 'core-independent' ),
			[ 'is_in_core' => 1, 'is_checksumfail' => 1 ]
		);
		$unidentified = $this->seedAfsFinding(
			$initialScanID,
			$core,
			$this->findingPath( 'unidentified-independent' ),
			[ 'is_in_wproot' => 1, 'is_unidentified' => 1 ]
		);
		$scanID = $this->insertAfsScan( 'full', '', [
			ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY,
			ScanActionVO::COVERAGE_FAMILY_CORE_INTEGRITY,
			ScanActionVO::COVERAGE_FAMILY_WPROOT_UNIDENTIFIED,
			ScanActionVO::COVERAGE_FAMILY_MALWARE,
		], 'manual' );
		$record = self::con()->db_con->scans->getQuerySelector()->byId( $scanID );
		$meta = $record->meta;
		$meta[ 'asset_snapshot_eligibility' ] = [
			'plugin' => [
				'mixed/plugin.php' => [
					'comparison_eligible' => true,
				],
			],
			'theme'  => [],
		];
		$this->replaceScanMeta( $scanID, $meta );

		$this->assertTrue( ( new SetScanCompleted() )->run( $scanID ) );
		$this->assertFindingResolved( $mixed, false );
		$this->assertSame( '0', $this->resultItemMetaValue( (int)$mixed[ 'result_item_id' ], 'is_mal' ) );
		$this->assertSame( '1', $this->resultItemMetaValue( (int)$mixed[ 'result_item_id' ], 'is_checksumfail' ) );
		$this->assertFindingResolved( $pureMalware, true, 'clean_rescan' );
		$this->assertFindingResolved( $coreFinding, true, 'clean_rescan' );
		$this->assertFindingResolved( $unidentified, true, 'clean_rescan' );
	}

	public function test_full_completion_chunks_more_than_one_hundred_eligible_assets() :void {
		$plugin = $this->afsAssetScenario( 'plugin' );
		$initialScanID = TestDataFactory::insertCompletedScan( 'afs' );
		$eligibility = [];
		for ( $i = 0; $i < 101; $i++ ) {
			$eligibility[ \sprintf( 'chunk/plugin-%03d.php', $i ) ] = [
				'version'             => '1.0',
				'comparison_eligible' => true,
			];
		}
		$finding = $this->seedAfsFinding(
			$initialScanID,
			$plugin,
			$this->findingPath( 'eligible-second-chunk' ),
			[
				'is_in_plugin'    => 1,
				'is_checksumfail' => 1,
				'ptg_slug'        => 'chunk/plugin-100.php',
				'asset_version'   => '1.0',
			]
		);
		$scanID = $this->insertAfsScan( 'full', '', [
			ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY,
		], 'manual' );
		$this->setFullScanEligibility( $scanID, [ 'plugin' => $eligibility ] );

		$this->assertTrue( ( new SetScanCompleted() )->run( $scanID ) );
		$this->assertFindingResolved( $finding, true, 'clean_rescan' );
	}

	/**
	 * @dataProvider provideInvalidCoverageMetadata
	 * @param array<string,mixed> $scanMeta
	 */
	public function test_invalid_coverage_metadata_fails_closed( array $scanMeta ) :void {
		$plugin = $this->afsAssetScenario( 'plugin' );
		$initialScanID = TestDataFactory::insertCompletedScan( 'afs' );
		$finding = $this->seedAfsFinding( $initialScanID, $plugin, $this->findingPath( 'invalid-coverage' ), [
			'is_in_plugin'    => 1,
			'is_checksumfail' => 1,
			'ptg_slug'        => $plugin[ 'asset_key' ],
		] );

		$scanID = $this->insertAfsScan( 'full', '', [
			ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY,
		], 'manual' );
		$this->replaceScanMeta( $scanID, $scanMeta );
		$this->assertTrue( ( new SetScanCompleted() )->run( $scanID ) );

		$this->assertFindingResolved( $finding, false );
	}

	public function provideInvalidCoverageMetadata() :array {
		$plugin = ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY;
		return [
			'missing'     => [ [] ],
			'empty'       => [ [ 'coverage_families' => [] ] ],
			'associative' => [ [ 'coverage_families' => [ 'family' => $plugin ] ] ],
			'non-string'  => [ [ 'coverage_families' => [ $plugin, 123 ] ] ],
			'duplicate'   => [ [ 'coverage_families' => [ $plugin, $plugin ] ] ],
			'unknown'     => [ [ 'coverage_families' => [ 'unknown_family' ] ] ],
		];
	}

	public function test_queue_completion_uses_canonical_persisted_coverage_not_queue_or_current_options() :void {
		$plugin = $this->afsAssetScenario( 'plugin' );
		$initialScanID = TestDataFactory::insertCompletedScan( 'afs' );
		$finding = $this->seedAfsFinding( $initialScanID, $plugin, $this->findingPath( 'canonical-meta' ), [
			'is_in_plugin'    => 1,
			'is_checksumfail' => 1,
			'ptg_slug'        => $plugin[ 'asset_key' ],
		] );
		$scanID = $this->insertAfsScan( 'plugin', $plugin[ 'asset_key' ], [
			ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY,
		], 'manual' );
		self::con()->opts->optSet( 'file_scan_areas', [] )->store();

		$queueItem = new QueueItemVO();
		$queueItem->scan_id = $scanID;
		$queueItem->scan = 'afs';
		$queueItem->scope_type = 'plugin';
		$queueItem->scope_key = $plugin[ 'asset_key' ];
		$queueItem->run_trigger = 'manual';
		$queueItem->meta = [
			'coverage_families' => [ ScanActionVO::COVERAGE_FAMILY_MALWARE ],
		];

		$this->assertTrue( ( new SetScanCompleted() )->runForQueueItem( $queueItem ) );
		$this->assertFindingResolved( $finding, true, 'clean_rescan' );
	}

	public function test_unfinished_queue_item_prevents_completion_and_resolution() :void {
		$plugin = $this->afsAssetScenario( 'plugin' );
		$initialScanID = TestDataFactory::insertCompletedScan( 'afs' );
		$finding = $this->seedAfsFinding( $initialScanID, $plugin, $this->findingPath( 'unfinished' ), [
			'is_in_plugin'    => 1,
			'is_checksumfail' => 1,
			'ptg_slug'        => $plugin[ 'asset_key' ],
		] );
		$scanID = $this->insertAfsScan( 'plugin', $plugin[ 'asset_key' ], [
			ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY,
		], 'manual' );
		$item = self::con()->db_con->scan_items->getRecord();
		$item->scan_ref = $scanID;
		$item->items = [ 'pending' ];
		$item->item_count = 1;
		$item->started_at = 0;
		$item->attempts = 0;
		$item->finished_at = 0;
		$this->assertTrue( self::con()->db_con->scan_items->getQueryInserter()->insert( $item ) );

		$this->assertFalse( ( new SetScanCompleted() )->run( $scanID ) );
		$this->assertFindingResolved( $finding, false );
	}

	/**
	 * @dataProvider provideCleanRescanCompletionScopes
	 */
	public function test_scan_completion_uses_clean_rescan_for_non_asset_change_runs(
		string $scopeType,
		string $scopeKey,
		string $runTrigger
	) :void {
		$scenario = $this->afsAssetScenario( 'plugin' );
		$initialScanId = TestDataFactory::insertCompletedScan( 'afs' );
		$findingMeta = $scenario[ 'meta' ];
		if ( $scopeType === 'full' ) {
			$findingMeta[ 'asset_version' ] = '1.0';
		}
		$stale = $this->seedAfsFinding(
			$initialScanId,
			$scenario,
			$scenario[ 'stale_path_full' ],
			$findingMeta
		);
		$scanScopeKey = $scopeKey === '{plugin}' ? $scenario[ 'scope_key' ] : $scopeKey;

		$scanId = $this->insertAfsScan( $scopeType, $scanScopeKey, [
			ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY,
		], $runTrigger );
		if ( $scopeType === 'full' ) {
			$this->setFullScanEligibility( $scanId, [
				'plugin' => [
					$scenario[ 'asset_key' ] => [
						'version'             => '1.0',
						'comparison_eligible' => true,
					],
				],
			] );
		}
		$this->assertTrue( ( new SetScanCompleted() )->run( $scanId ) );
		$item = self::con()->db_con->scan_result_items->getQuerySelector()
			->byId( (int)$stale[ 'result_item_id' ] );

		$this->assertGreaterThan( 0, (int)$item->resolved_at );
		$this->assertSame( 'clean_rescan', (string)$item->resolution_reason );
	}

	public function provideCleanRescanCompletionScopes() :array {
		return [
			'full manual'    => [ 'full', '', 'manual' ],
			'scoped manual'  => [ 'plugin', '{plugin}', 'manual' ],
		];
	}

	/**
	 * @param array{result_item_id:int} $finding
	 */
	private function assertFindingResolved( array $finding, bool $resolved, string $reason = '' ) :void {
		$item = self::con()->db_con->scan_result_items->getQuerySelector()
			->byId( (int)$finding[ 'result_item_id' ] );
		$this->assertNotEmpty( $item );
		$this->assertSame( $resolved, (int)$item->resolved_at > 0 );
		$this->assertSame( $reason, (string)$item->resolution_reason );
	}

	/**
	 * @param array<string,mixed> $meta
	 */
	private function replaceScanMeta( int $scanID, array $meta ) :void {
		$record = self::con()->db_con->scans->getQuerySelector()->byId( $scanID );
		$this->assertNotEmpty( $record );
		$record->meta = $meta;
		$raw = $record->getRawData();
		$this->assertArrayHasKey( 'meta', $raw );
		$this->assertTrue( self::con()->db_con->scans->getQueryUpdater()->updateById( $scanID, [
			'meta' => $raw[ 'meta' ],
		] ) );
	}

	/**
	 * @param array<string,array<string,array{version:string,comparison_eligible:bool}>> $eligibility
	 */
	private function setFullScanEligibility( int $scanID, array $eligibility ) :void {
		$record = self::con()->db_con->scans->getQuerySelector()->byId( $scanID );
		$this->assertNotEmpty( $record );
		$meta = $record->meta;
		$meta[ 'asset_snapshot_eligibility' ] = \array_merge( [
			'plugin' => [],
			'theme'  => [],
		], $eligibility );
		$this->replaceScanMeta( $scanID, $meta );
	}

	private function resultItemMetaValue( int $resultItemID, string $metaKey ) :string {
		global $wpdb;
		return (string)$wpdb->get_var( $wpdb->prepare(
			"SELECT `meta_value`
				FROM `".self::con()->db_con->scan_result_item_meta->getTable()."`
				WHERE `ri_ref`=%d
				  AND `meta_key`=%s",
			$resultItemID,
			$metaKey
		) );
	}

	private function findingPath( string $suffix ) :string {
		return \wp_normalize_path( \path_join( ABSPATH, 'shield-coverage-'.$suffix.'.php' ) );
	}

	private function buildAlertReport() :ReportVO {
		$carbon = Services::Request()->carbon( true )->subDay();

		$report = new ReportVO();
		$report->type = Constants::REPORT_TYPE_ALERT;
		$report->interval = 'daily';
		$report->title = 'Alert :: Daily :: Auto-Generated';
		$report->start_at = ( clone $carbon )->startOfDay()->timestamp;
		$report->end_at = ( clone $carbon )->endOfDay()->timestamp;
		$report->areas = [
			Constants::REPORT_AREA_SCANS => [ 'scan_results' ],
		];

		return $report;
	}

	private function scanResultsRow( ReportVO $report, string $slug ) :array {
		foreach ( $report->areas_data[ Constants::REPORT_AREA_SCANS ][ 'scan_results' ] as $row ) {
			if ( $row[ 'slug' ] === $slug ) {
				return $row;
			}
		}

		$this->fail( \sprintf( 'Scan result row missing for slug: %s', $slug ) );
		return [];
	}
}
