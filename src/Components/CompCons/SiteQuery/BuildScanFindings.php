<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-type ScanFindingsResult array{
 *   total:int,
 *   items:list<array<string,mixed>>
 * }
 * @phpstan-type ScanFindingsQuery array{
 *   generated_at:int,
 *   is_available:bool,
 *   message:string,
 *   filters:array{
 *     scan_slugs:list<string>,
 *     states:list<string>
 *   },
 *   results:array<string,ScanFindingsResult>
 * }
 */
class BuildScanFindings {

	use PluginControllerConsumer;

	public const SUPPORTED_STATES = [
		'is_checksumfail',
		'is_unrecognised',
		'is_mal',
		'is_missing',
		'is_abandoned',
		'is_vulnerable',
	];

	/**
	 * @param string[] $scanSlugs
	 * @param string[] $statesToInclude
	 * @return ScanFindingsQuery
	 */
	public function build( array $scanSlugs = [], array $statesToInclude = [] ) :array {
		$scanSlugs = $this->sanitizeScanSlugs( $scanSlugs );
		$statesToInclude = $this->sanitizeStatesToInclude( $statesToInclude );

		$query = [
			'generated_at' => Services::Request()->ts(),
			'is_available' => $this->isAvailable(),
			'message'      => '',
			'filters'      => [
				'scan_slugs' => $scanSlugs,
				'states'     => $statesToInclude,
			],
			'results'      => [],
		];

		if ( !$query[ 'is_available' ] ) {
			$query[ 'message' ] = __( 'Results are unavailable while scans are currently running.', 'wp-simple-firewall' );
			return $query;
		}

		foreach ( $scanSlugs as $scanSlug ) {
			$items = \array_values( \array_filter(
				\array_map( [ $this, 'normalizeResultItem' ], $this->getRawScanItems( $scanSlug, $statesToInclude ) ),
				fn( array $item ) :bool => $this->shouldIncludeItem( $item, $statesToInclude )
			) );

			$query[ 'results' ][ $scanSlug ] = [
				'total' => \count( $items ),
				'items' => $items,
			];
		}

		\ksort( $query[ 'results' ] );
		return $query;
	}

	protected function isAvailable() :bool {
		return self::con()->comps->site_query->scanRuntime()[ 'enqueued_count' ] < 1;
	}

	/**
	 * @return string[]
	 */
	protected function getScanSlugs() :array {
		return self::con()->comps->scans->getScanSlugs();
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	protected function getRawScanItems( string $scanSlug, array $statesToInclude = [] ) :array {
		$scanCon = self::con()->comps->scans->getScanCon( $scanSlug );
		if ( $scanCon === null ) {
			return [];
		}

		$rawItems = [];
		$resultsSet = ( new RetrieveItems() )
			->setScanController( $scanCon )
			->retrieveLatestForFindings( $statesToInclude );

		foreach ( $resultsSet->getAllItems() as $item ) {
			$rawItems[] = \array_merge(
				$item->getRawData(),
				\array_intersect_key(
					$item->VO->getRawData(),
					\array_flip( [
						'ignored_at',
						'notified_at',
						'attempt_repair_at',
						'item_repaired_at',
						'item_deleted_at',
						'item_id',
						'item_type',
					] )
				)
			);
		}

		return $rawItems;
	}

	/**
	 * @param string[] $scanSlugs
	 * @return list<string>
	 */
	private function sanitizeScanSlugs( array $scanSlugs ) :array {
		$possible = $this->getScanSlugs();
		$scanSlugs = \array_values( \array_intersect( $possible, $scanSlugs ) );
		return empty( $scanSlugs ) ? \array_values( $possible ) : $scanSlugs;
	}

	/**
	 * @param string[] $statesToInclude
	 * @return list<string>
	 */
	private function sanitizeStatesToInclude( array $statesToInclude ) :array {
		return \array_values( \array_intersect( self::SUPPORTED_STATES, $statesToInclude ) );
	}

	/**
	 * @param array<string,mixed> $item
	 * @return array<string,mixed>
	 */
	private function normalizeResultItem( array $item ) :array {
		\ksort( $item );
		return $item;
	}

	/**
	 * @param array<string,mixed> $item
	 * @param string[] $statesToInclude
	 */
	private function shouldIncludeItem( array $item, array $statesToInclude ) :bool {
		if ( empty( $statesToInclude ) ) {
			return true;
		}

		foreach ( $statesToInclude as $itemState ) {
			if ( !empty( $item[ $itemState ] ) ) {
				return true;
			}
		}

		return false;
	}
}
