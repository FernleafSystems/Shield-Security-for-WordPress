<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-type GroupedAssetType 'plugin'|'theme'
 * @phpstan-type GroupedAssetSummaryRow array{
 *   slug:string,
 *   file_count:int
 * }
 */
class RetrieveGroupedAssetSummaries {

	use PluginControllerConsumer;

	private ?LatestScanResultWheresBuilder $latestScanWheresBuilder = null;

	/**
	 * @phpstan-param GroupedAssetType $assetType
	 * @param array<string,mixed> $resultsDisplayOptions
	 * @return list<GroupedAssetSummaryRow>
	 */
	public function retrieve( string $assetType, array $resultsDisplayOptions = [] ) :array {
		$query = $this->buildQuery(
			$assetType,
			$this->latestScanWheresBuilder()->forResultsDisplayWithOptions(
				'afs',
				$resultsDisplayOptions
			),
			"SELECT `ri`.`asset_key` AS `slug`, COUNT(DISTINCT `ri`.`id`) AS `file_count`",
			'GROUP BY `ri`.`asset_key`
			ORDER BY `file_count` DESC, `ri`.`asset_key` ASC'
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

	/**
	 * @phpstan-param GroupedAssetType $assetType
	 * @param list<string> $wheres
	 */
	private function buildQuery( string $assetType, array $wheres, string $selectSql, string $suffixSql = '' ) :string {
		$dbCon = self::con()->db_con;
		$assetType = \preg_replace( '/[^a-z]/i', '', $assetType ) ?? '';
		$wheres[] = \sprintf( "`ri`.`asset_type`='%s'", $assetType );
		$wheres[] = "`ri`.`asset_key`!=''";

		return \trim( \sprintf(
			"%s
			FROM `%s` AS `ri`
			WHERE %s
			%s",
			$selectSql,
			$dbCon->scan_result_items->getTable(),
			\implode( ' AND ', $wheres ),
			$suffixSql
		) );
	}

	private function latestScanWheresBuilder() :LatestScanResultWheresBuilder {
		return $this->latestScanWheresBuilder ??= new LatestScanResultWheresBuilder();
	}
}
