<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-type ScanFindingsItem array{
 *   item_id:string,
 *   states:list<string>,
 *   is_ignored:bool,
 *   scope?:'core'|'plugin'|'theme'|'other',
 *   asset_slug?:string
 * }
 * @phpstan-type ScanFindingsResult array{
 *   total:int,
 *   items:list<ScanFindingsItem>
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
			$rawItems[] = [
				'item_id'    => (string)( $item->VO->item_id ?? '' ),
				'ignored_at' => (int)( $item->VO->ignored_at ?? 0 ),
				'states'     => $this->extractSupportedStates( $item ),
				'scope'      => $scanSlug === 'afs' ? $this->determineScope( $item ) : '',
				'asset_slug' => $scanSlug === 'afs' ? $this->determineAssetSlug( $item ) : '',
			];
		}

		return $rawItems;
	}

	/**
	 * @param string[] $scanSlugs
	 * @return list<string>
	 */
	private function sanitizeScanSlugs( array $scanSlugs ) :array {
		$possible = $this->getScanSlugs();
		$requested = \array_values( \array_unique( \array_filter( \array_map(
			static fn( $slug ) :string => \trim( (string)$slug ),
			$scanSlugs
		), static fn( string $slug ) :bool => $slug !== '' ) ) );
		if ( empty( $requested ) ) {
			return \array_values( $possible );
		}

		$invalid = \array_values( \array_diff( $requested, $possible ) );
		if ( !empty( $invalid ) ) {
			throw new \InvalidArgumentException(
				\sprintf( 'Invalid scan slugs provided. Please only supply: %s', \implode( ', ', $possible ) )
			);
		}

		return \array_values( \array_intersect( $possible, $requested ) );
	}

	/**
	 * @param string[] $statesToInclude
	 * @return list<string>
	 */
	private function sanitizeStatesToInclude( array $statesToInclude ) :array {
		$requested = \array_values( \array_unique( \array_filter( \array_map(
			static fn( $state ) :string => \trim( (string)$state ),
			$statesToInclude
		), static fn( string $state ) :bool => $state !== '' ) ) );
		if ( empty( $requested ) ) {
			return [];
		}

		$invalid = \array_values( \array_diff( $requested, self::SUPPORTED_STATES ) );
		if ( !empty( $invalid ) ) {
			throw new \InvalidArgumentException(
				\sprintf( 'Invalid scan item states provided. Please only supply: %s', \implode( ', ', self::SUPPORTED_STATES ) )
			);
		}

		return \array_values( \array_intersect( self::SUPPORTED_STATES, $requested ) );
	}

	/**
	 * @param array<string,mixed> $item
	 * @return ScanFindingsItem
	 */
	private function normalizeResultItem( array $item ) :array {
		$normalized = [
			'item_id'    => (string)( $item[ 'item_id' ] ?? '' ),
			'states'     => \array_values( \array_filter( \is_array( $item[ 'states' ] ?? null ) ? $item[ 'states' ] : [] ) ),
			'is_ignored' => !empty( $item[ 'ignored_at' ] ),
		];

		$scope = (string)( $item[ 'scope' ] ?? '' );
		if ( \in_array( $scope, [ 'core', 'plugin', 'theme', 'other' ], true ) ) {
			$normalized[ 'scope' ] = $scope;
		}

		$assetSlug = \trim( (string)( $item[ 'asset_slug' ] ?? '' ) );
		if ( $assetSlug !== '' ) {
			$normalized[ 'asset_slug' ] = $assetSlug;
		}

		return $normalized;
	}

	/**
	 * @param array<string,mixed> $item
	 * @param string[] $statesToInclude
	 */
	private function shouldIncludeItem( array $item, array $statesToInclude ) :bool {
		if ( empty( $statesToInclude ) ) {
			return true;
		}

		return \count( \array_intersect(
			$statesToInclude,
			\is_array( $item[ 'states' ] ?? null ) ? $item[ 'states' ] : []
		) ) > 0;
	}

	private function determineScope( $item ) :string {
		if ( !empty( $item->is_in_core ) ) {
			return 'core';
		}
		if ( !empty( $item->is_in_plugin ) ) {
			return 'plugin';
		}
		if ( !empty( $item->is_in_theme ) ) {
			return 'theme';
		}
		return 'other';
	}

	private function determineAssetSlug( $item ) :string {
		return !empty( $item->is_in_plugin ) || !empty( $item->is_in_theme )
			? \trim( (string)( $item->ptg_slug ?? '' ) )
			: '';
	}

	/**
	 * @return list<string>
	 */
	private function extractSupportedStates( $item ) :array {
		return \array_values( \array_filter(
			self::SUPPORTED_STATES,
			static fn( string $state ) :bool => !empty( $item->{$state} )
		) );
	}
}
