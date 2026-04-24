<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops\Handler as ResultItemsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveCount;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\LatestScanResultWheresBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveGroupedAssetSummaries;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-type AdminBarExactScanCounts array{
 *   malware:int,
 *   wp_files:int,
 *   plugin_files:int,
 *   theme_files:int,
 *   abandoned:int,
 *   vulnerable_assets:int
 * }
 * @phpstan-type AdminBarScanSummary array{
 *   counts:array<string,int>,
 *   total:int,
 *   is_capped:bool
 * }
 */
class Counts {

	use PluginControllerConsumer;

	private const ADMIN_BAR_EXACT_COUNT_KEYS = [
		'malware_files',
		'wp_files',
		'plugin_files',
		'theme_files',
		'abandoned',
		'assets_vulnerable',
	];

	private const ADMIN_BAR_BOUNDED_LIMIT = 100;

	private array $counts = [];

	private int $context;

	private ?RetrieveGroupedAssetSummaries $groupedAssetSummaries = null;

	private ?LatestScanResultWheresBuilder $latestScanWheresBuilder = null;

	public function __construct( int $context = RetrieveCount::CONTEXT_ACTIVE_PROBLEMS ) {
		$this->context = $context;
	}

	public function all() :array {
		\array_map( fn( string $type ) => $this->getCount( $type ), [
			'malware_files',
			'abandoned',
			'plugin_files',
			'theme_files',
			'assets_vulnerable',
			'wp_files',
		] );
		return $this->counts;
	}

	public function countMalware() :int {
		return $this->getCount( 'malware_files' );
	}

	public function countAbandoned() :int {
		return $this->getCount( 'abandoned' );
	}

	public function countPluginFiles() :int {
		return $this->getCount( 'plugin_files' );
	}

	public function countThemeFiles() :int {
		return $this->getCount( 'theme_files' );
	}

	public function countVulnerableAssets() :int {
		return $this->getCount( 'assets_vulnerable' );
	}

	public function countWPFiles() :int {
		return $this->getCount( 'wp_files' );
	}

	/**
	 * @return AdminBarScanSummary
	 */
	public function adminBarScanSummary( bool $forceExact = false ) :array {
		if ( $forceExact || $this->hasWarmAdminBarExactCounts() ) {
			$counts = $this->adminBarExactCounts();

			return [
				'counts'    => $counts,
				'total'     => (int)\array_sum( $counts ),
				'is_capped' => false,
			];
		}

		$count = $this->adminBarBoundedActiveProblemCount( self::ADMIN_BAR_BOUNDED_LIMIT );

		return [
			'counts'    => [],
			'total'     => \min( $count, self::ADMIN_BAR_BOUNDED_LIMIT - 1 ),
			'is_capped' => $count >= self::ADMIN_BAR_BOUNDED_LIMIT,
		];
	}

	private function hasWarmAdminBarExactCounts() :bool {
		foreach ( self::ADMIN_BAR_EXACT_COUNT_KEYS as $key ) {
			if ( !\array_key_exists( $key, $this->counts ) ) {
				return false;
			}
		}

		return true;
	}

	public function countAffectedPluginAssets() :int {
		$cacheKey = 'count_affected_plugin_assets';
		if ( !isset( $this->counts[ $cacheKey ] ) ) {
			$this->counts[ $cacheKey ] = $this->groupedAssetSummaries()
				->countForContext( 'plugin', $this->context );
		}

		return (int)$this->counts[ $cacheKey ];
	}

	public function countAffectedThemeAssets() :int {
		$cacheKey = 'count_affected_theme_assets';
		if ( !isset( $this->counts[ $cacheKey ] ) ) {
			$this->counts[ $cacheKey ] = $this->groupedAssetSummaries()
				->countForContext( 'theme', $this->context );
		}

		return (int)$this->counts[ $cacheKey ];
	}

	public function countDistinctVulnerableAssets() :int {
		return $this->countDistinctItemIdsForScanMeta(
			self::con()->comps->scans->WPV()->getSlug(),
			'is_vulnerable'
		);
	}

	public function countDistinctAbandonedAssets() :int {
		return $this->countDistinctItemIdsForScanMeta(
			self::con()->comps->scans->APC()->getSlug(),
			'is_abandoned'
		);
	}

	public function countDistinctVulnerabilityReviewAssets() :int {
		$cacheKey = 'count_distinct_vulnerability_review_assets';
		if ( !isset( $this->counts[ $cacheKey ] ) ) {
			$this->counts[ $cacheKey ] = $this->countDistinctItemIdsAcrossScanMetaFilters( [
				[
					'scan_slug' => self::con()->comps->scans->WPV()->getSlug(),
					'meta_key'  => 'is_vulnerable',
				],
				[
					'scan_slug' => self::con()->comps->scans->APC()->getSlug(),
					'meta_key'  => 'is_abandoned',
				],
			] );
		}

		return (int)$this->counts[ $cacheKey ];
	}

	private function getCount( string $resultType ) :int {

		if ( !isset( $this->counts[ $resultType ] ) ) {
			$scansCon = self::con()->comps->scans;
			$resultsCount = new RetrieveCount();

			switch ( $resultType ) {

				case 'malware_files':
					$resultsCount->setScanController( $scansCon->AFS() )
								 ->addWheres( $this->resultMetaFlagWheres( 'is_mal' ) );
					break;
				case 'wp_files':
					$resultsCount->setScanController( $scansCon->AFS() )
								 ->addWheres( $this->coreFileWheres() );
					break;
				case 'plugin_files':
					$resultsCount->setScanController( $scansCon->AFS() )
								 ->addWheres( $this->pluginFileWheres() );
					break;
				case 'theme_files':
					$resultsCount->setScanController( $scansCon->AFS() )
								 ->addWheres( $this->themeFileWheres() );
					break;
				case 'abandoned':
					$resultsCount->setScanController( $scansCon->APC() )
								 ->addWheres( $this->resultMetaFlagWheres( 'is_abandoned' ) );
					break;
				case 'assets_vulnerable':
					$resultsCount->setScanController( $scansCon->WPV() )
								 ->addWheres( $this->resultMetaFlagWheres( 'is_vulnerable' ) );
					break;

				default:
					die( __( 'Unsupported result type.', 'wp-simple-firewall' ) );
			}
			$this->counts[ $resultType ] = $resultsCount->count( $this->context );
		}

		return $this->counts[ $resultType ];
	}

	/**
	 * @return AdminBarExactScanCounts
	 */
	private function adminBarExactCounts() :array {
		return [
			'malware'           => $this->countMalware(),
			'wp_files'          => $this->countWPFiles(),
			'plugin_files'      => $this->countPluginFiles(),
			'theme_files'       => $this->countThemeFiles(),
			'abandoned'         => $this->countAbandoned(),
			'vulnerable_assets' => $this->countVulnerableAssets(),
		];
	}

	private function adminBarBoundedActiveProblemCount( int $limit ) :int {
		$cacheKey = 'admin_bar_bounded_active_problem_count_'.$limit;
		if ( !isset( $this->counts[ $cacheKey ] ) ) {
			$scanWheres = [];
			foreach ( self::con()->comps->scans->getAllScanCons() as $scanCon ) {
				if ( !$scanCon->isEnabled() ) {
					continue;
				}

				$latestScanId = $this->getLatestScanId( $scanCon->getSlug() );
				if ( $latestScanId > 0 ) {
					$scanWheres[] = '('.\implode( ' AND ', $this->getLatestScanWheresBuilder()->forContext( $latestScanId, $this->context ) ).')';
				}
			}

			$this->counts[ $cacheKey ] = empty( $scanWheres )
				? 0
				: $this->countBoundedActiveProblemRows( $scanWheres, $limit );
		}

		return (int)$this->counts[ $cacheKey ];
	}

	/**
	 * @param list<string> $scanWheres
	 */
	private function countBoundedActiveProblemRows( array $scanWheres, int $limit ) :int {
		$dbCon = self::con()->db_con;

		return (int)Services::WpDb()->getVar( \sprintf(
			"SELECT COUNT(*) FROM (
				SELECT `ri`.`id`
				FROM `%s` AS `sr`
				INNER JOIN `%s` AS `ri`
					ON `sr`.`resultitem_ref`=`ri`.`id`
				WHERE (%s)
				LIMIT %d
			) AS `bounded_scan_results`",
			$dbCon->scan_results->getTable(),
			$dbCon->scan_result_items->getTable(),
			\implode( ' OR ', $scanWheres ),
			\max( 1, $limit )
		) );
	}

	/**
	 * @return list<string>
	 */
	private function coreFileWheres() :array {
		return [
			"`ri`.`item_type`='".ResultItemsHandler::ITEM_TYPE_FILE."'",
			$this->resultMetaFlagExists( 'is_in_core' ),
		];
	}

	/**
	 * @return list<string>
	 */
	private function pluginFileWheres() :array {
		return [
			"`ri`.`item_type`='".ResultItemsHandler::ITEM_TYPE_FILE."'",
			$this->resultMetaFlagExists( 'is_in_plugin' ),
		];
	}

	/**
	 * @return list<string>
	 */
	private function themeFileWheres() :array {
		return [
			"`ri`.`item_type`='".ResultItemsHandler::ITEM_TYPE_FILE."'",
			$this->resultMetaFlagExists( 'is_in_theme' ),
		];
	}

	private function resultMetaFlagExists( string $metaKey ) :string {
		return \sprintf(
			"EXISTS (
				SELECT 1
				FROM `%s` AS `rim_filter`
				WHERE `rim_filter`.`ri_ref`=`ri`.`id`
					AND `rim_filter`.`meta_key`='%s'
					AND `rim_filter`.`meta_value`=1
			)",
			self::con()->db_con->scan_result_item_meta->getTable(),
			$metaKey
		);
	}

	/**
	 * @return list<string>
	 */
	private function resultMetaFlagWheres( string $metaKey ) :array {
		return [
			"`rim`.`meta_key`='".$metaKey."'",
			"`rim`.`meta_value`=1",
		];
	}

	private function countDistinctItemIdsForScanMeta( string $scanSlug, string $metaKey ) :int {
		$cacheKey = 'count_distinct_item_id_'.$scanSlug.'_'.$metaKey;
		if ( !isset( $this->counts[ $cacheKey ] ) ) {
			$this->counts[ $cacheKey ] = $this->countDistinctColumnValues(
				$scanSlug,
				"`ri`.`item_id`",
				[
					[
						'alias' => 'rim',
						'on'    => \sprintf(
							"`rim`.`ri_ref`=`ri`.`id` AND `rim`.`meta_key`='%s' AND `rim`.`meta_value`=1",
							$metaKey
						),
					],
				]
			);
		}

		return (int)$this->counts[ $cacheKey ];
	}

	/**
	 * @param list<array{alias:string,on:string}> $joins
	 */
	private function countDistinctColumnValues( string $scanSlug, string $selectColumn, array $joins ) :int {
		$latestScanId = $this->getLatestScanId( $scanSlug );
		if ( $latestScanId < 1 ) {
			return 0;
		}

		$dbCon = self::con()->db_con;
		$joinSql = \implode( ' ', \array_map(
			static fn( array $join ) :string => \sprintf(
				"INNER JOIN `%s` AS `%s` ON %s",
				$dbCon->scan_result_item_meta->getTable(),
				$join[ 'alias' ],
				$join[ 'on' ]
			),
			$joins
		) );
		$query = \sprintf(
			"SELECT COUNT(DISTINCT %s)
			FROM `%s` AS `sr`
			INNER JOIN `%s` AS `ri`
				ON `sr`.`resultitem_ref`=`ri`.`id`
			%s
			WHERE %s",
			$selectColumn,
			$dbCon->scan_results->getTable(),
			$dbCon->scan_result_items->getTable(),
			$joinSql,
			\implode( ' AND ', $this->getLatestScanWheresBuilder()->forContext( $latestScanId, $this->context ) )
		);

		return (int)Services::WpDb()->getVar( $query );
	}

	/**
	 * @param list<array{scan_slug:string,meta_key:string}> $filters
	 */
	private function countDistinctItemIdsAcrossScanMetaFilters( array $filters ) :int {
		$queries = [];
		$dbCon = self::con()->db_con;

		foreach ( $filters as $filter ) {
			$latestScanId = $this->getLatestScanId( $filter[ 'scan_slug' ] );
			if ( $latestScanId < 1 ) {
				continue;
			}

			$queries[] = \sprintf(
				"SELECT DISTINCT `ri`.`item_id`
				FROM `%s` AS `sr`
				INNER JOIN `%s` AS `ri`
					ON `sr`.`resultitem_ref`=`ri`.`id`
				INNER JOIN `%s` AS `rim`
					ON `rim`.`ri_ref`=`ri`.`id`
				WHERE %s AND `rim`.`meta_key`='%s' AND `rim`.`meta_value`=1",
				$dbCon->scan_results->getTable(),
				$dbCon->scan_result_items->getTable(),
				$dbCon->scan_result_item_meta->getTable(),
				\implode( ' AND ', $this->getLatestScanWheresBuilder()->forContext( $latestScanId, $this->context ) ),
				$filter[ 'meta_key' ]
			);
		}

		if ( empty( $queries ) ) {
			return 0;
		}

		return (int)Services::WpDb()->getVar(
			\sprintf(
				'SELECT COUNT(*) FROM (%s) AS `combined_items`',
				\implode( ' UNION ', $queries )
			)
		);
	}

	private function getLatestScanId( string $scanSlug ) :int {
		$latest = self::con()->db_con->scans->getQuerySelector()->getLatestForScan( $scanSlug );
		return empty( $latest ) ? 0 : (int)$latest->id;
	}

	private function getLatestScanWheresBuilder() :LatestScanResultWheresBuilder {
		return $this->latestScanWheresBuilder ??= new LatestScanResultWheresBuilder();
	}

	private function groupedAssetSummaries() :RetrieveGroupedAssetSummaries {
		return $this->groupedAssetSummaries ??= new RetrieveGroupedAssetSummaries();
	}
}
