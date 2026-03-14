<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveCount;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\LatestScanResultWheresBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Counts {

	use PluginControllerConsumer;

	private array $counts = [];

	private int $context;

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

	public function countAffectedPluginAssets() :int {
		return \count( $this->getDistinctAssetSlugsForAfsMeta( 'is_in_plugin' ) );
	}

	public function countAffectedThemeAssets() :int {
		return \count( $this->getDistinctAssetSlugsForAfsMeta( 'is_in_theme' ) );
	}

	public function countDistinctVulnerableAssets() :int {
		return \count( $this->getDistinctItemIdsForScanMeta( self::con()->comps->scans->WPV()->getSlug(), 'is_vulnerable' ) );
	}

	public function countDistinctAbandonedAssets() :int {
		return \count( $this->getDistinctItemIdsForScanMeta( self::con()->comps->scans->APC()->getSlug(), 'is_abandoned' ) );
	}

	public function countDistinctVulnerabilityReviewAssets() :int {
		return \count( \array_unique( \array_merge(
			$this->getDistinctItemIdsForScanMeta( self::con()->comps->scans->WPV()->getSlug(), 'is_vulnerable' ),
			$this->getDistinctItemIdsForScanMeta( self::con()->comps->scans->APC()->getSlug(), 'is_abandoned' )
		) ) );
	}

	private function getCount( $resultType ) :int {

		if ( !isset( $this->counts[ $resultType ] ) ) {
			$scansCon = self::con()->comps->scans;
			$resultsCount = new RetrieveCount();

			switch ( $resultType ) {

				case 'malware_files':
					$resultsCount->setScanController( $scansCon->AFS() )
								 ->addWheres( [ "`rim`.`meta_key`='is_mal'", ] );
					break;
				case 'wp_files':
					$resultsCount->setScanController( $scansCon->AFS() )
								 ->addWheres( [ "`rim`.`meta_key`='is_in_core'", ] );
					break;
				case 'plugin_files':
					$resultsCount->setScanController( $scansCon->AFS() )
								 ->addWheres( [ "`rim`.`meta_key`='is_in_plugin'", ] );
					break;
				case 'theme_files':
					$resultsCount->setScanController( $scansCon->AFS() )
								 ->addWheres( [ "`rim`.`meta_key`='is_in_theme'", ] );
					break;
				case 'abandoned':
					$resultsCount->setScanController( $scansCon->APC() )
								 ->addWheres( [ "`rim`.`meta_key`='is_abandoned'", ] );
					break;
				case 'assets_vulnerable':
					$resultsCount->setScanController( $scansCon->WPV() )
								 ->addWheres( [ "`rim`.`meta_key`='is_vulnerable'", ] );
					break;

				default:
					die( __( 'Unsupported result type.', 'wp-simple-firewall' ) );
			}
			$this->counts[ $resultType ] = $resultsCount->count( $this->context );
		}

		return $this->counts[ $resultType ];
	}

	/**
	 * @return list<string>
	 */
	private function getDistinctAssetSlugsForAfsMeta( string $membershipMetaKey ) :array {
		$cacheKey = 'distinct_asset_slug_'.$membershipMetaKey;
		if ( !isset( $this->counts[ $cacheKey ] ) ) {
			$scanSlug = self::con()->comps->scans->AFS()->getSlug();
			$this->counts[ $cacheKey ] = $this->queryDistinctColumnValues(
				$scanSlug,
				"`slug_meta`.`meta_value`",
				[
					[
						'alias' => 'membership_meta',
						'on'    => \sprintf(
							"`membership_meta`.`ri_ref`=`ri`.`id` AND `membership_meta`.`meta_key`='%s'",
							$membershipMetaKey
						),
					],
					[
						'alias' => 'slug_meta',
						'on'    => "`slug_meta`.`ri_ref`=`ri`.`id` AND `slug_meta`.`meta_key`='ptg_slug' AND `slug_meta`.`meta_value`!=''",
					],
				]
			);
		}

		return $this->counts[ $cacheKey ];
	}

	/**
	 * @return list<string>
	 */
	private function getDistinctItemIdsForScanMeta( string $scanSlug, string $metaKey ) :array {
		$cacheKey = 'distinct_item_id_'.$scanSlug.'_'.$metaKey;
		if ( !isset( $this->counts[ $cacheKey ] ) ) {
			$this->counts[ $cacheKey ] = $this->queryDistinctColumnValues(
				$scanSlug,
				"`ri`.`item_id`",
				[
					[
						'alias' => 'rim',
						'on'    => \sprintf(
							"`rim`.`ri_ref`=`ri`.`id` AND `rim`.`meta_key`='%s'",
							$metaKey
						),
					],
				]
			);
		}

		return $this->counts[ $cacheKey ];
	}

	/**
	 * @param list<array{alias:string,on:string}> $joins
	 * @return list<string>
	 */
	private function queryDistinctColumnValues( string $scanSlug, string $selectColumn, array $joins ) :array {
		$latestScanId = $this->getLatestScanId( $scanSlug );
		if ( $latestScanId < 1 ) {
			return [];
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
			"SELECT DISTINCT %s
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

		return \array_values( \array_filter( \array_map(
			static fn( $value ) :string => (string)$value,
			Services::WpDb()->loadWpdb()->get_col( $query )
		), static fn( string $value ) :bool => $value !== '' ) );
	}

	private function getLatestScanId( string $scanSlug ) :int {
		$latest = self::con()->db_con->scans->getQuerySelector()->getLatestForScan( $scanSlug );
		return empty( $latest ) ? 0 : (int)$latest->id;
	}

	private function getLatestScanWheresBuilder() :LatestScanResultWheresBuilder {
		return $this->latestScanWheresBuilder ??= new LatestScanResultWheresBuilder();
	}
}
