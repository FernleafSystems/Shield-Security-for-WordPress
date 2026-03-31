<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-type GroupedAssetSummaryRow array{
 *   slug:string,
 *   file_count:int
 * }
 */
class RetrieveGroupedAssetSummaries {

	use PluginControllerConsumer;

	private ?LatestScanResultWheresBuilder $latestScanWheresBuilder = null;

	/**
	 * @param array<string,mixed>|null $resultsDisplayOptions
	 * @return list<GroupedAssetSummaryRow>
	 */
	public function retrieve( string $assetType, ?array $resultsDisplayOptions = null ) :array {
		$latestScanId = $this->getLatestAfsScanId();
		if ( $latestScanId < 1 ) {
			return [];
		}

		$membershipMetaKey = $assetType === 'plugin' ? 'is_in_plugin' : 'is_in_theme';
		$dbCon = self::con()->db_con;
		$query = \sprintf(
			"SELECT `slug_meta`.`meta_value` AS `slug`, COUNT(DISTINCT `ri`.`id`) AS `file_count`
			FROM `%s` AS `sr`
			INNER JOIN `%s` AS `ri`
				ON `sr`.`resultitem_ref`=`ri`.`id`
			INNER JOIN `%s` AS `membership_meta`
				ON `membership_meta`.`ri_ref`=`ri`.`id` AND `membership_meta`.`meta_key`='%s'
			INNER JOIN `%s` AS `slug_meta`
				ON `slug_meta`.`ri_ref`=`ri`.`id` AND `slug_meta`.`meta_key`='ptg_slug' AND `slug_meta`.`meta_value`!=''
			WHERE %s
			GROUP BY `slug_meta`.`meta_value`
			ORDER BY `file_count` DESC, `slug_meta`.`meta_value` ASC",
			$dbCon->scan_results->getTable(),
			$dbCon->scan_result_items->getTable(),
			$dbCon->scan_result_item_meta->getTable(),
			$membershipMetaKey,
			$dbCon->scan_result_item_meta->getTable(),
			\implode( ' AND ', $this->latestScanWheresBuilder()->forResultsDisplayWithOptions(
				$latestScanId,
				\is_array( $resultsDisplayOptions ) ? $resultsDisplayOptions : []
			) )
		);

		return \array_values( \array_filter( \array_map(
			static function ( $row ) :array {
				$row = \is_object( $row ) ? \get_object_vars( $row ) : ( \is_array( $row ) ? $row : [] );
				$slug = \trim( (string)( $row[ 'slug' ] ?? '' ) );
				return $slug === ''
					? []
					: [
						'slug'       => $slug,
						'file_count' => \max( 0, (int)( $row[ 'file_count' ] ?? 0 ) ),
					];
			},
			Services::WpDb()->selectCustom( $query )
		), static fn( array $row ) :bool => $row !== [] ) );
	}

	private function getLatestAfsScanId() :int {
		$latest = self::con()->db_con->scans->getQuerySelector()->getLatestForScan(
			self::con()->comps->scans->AFS()->getSlug()
		);
		return empty( $latest ) ? 0 : (int)$latest->id;
	}

	private function latestScanWheresBuilder() :LatestScanResultWheresBuilder {
		return $this->latestScanWheresBuilder ??= new LatestScanResultWheresBuilder();
	}
}
