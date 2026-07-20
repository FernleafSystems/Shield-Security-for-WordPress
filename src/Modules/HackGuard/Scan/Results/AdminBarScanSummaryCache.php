<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @phpstan-import-type AdminBarExactScanSummary from Counts
 * @phpstan-import-type AdminBarBoundedScanSummary from Counts
 */
class AdminBarScanSummaryCache {

	use PluginControllerConsumer;

	private const EXACT_TTL = 600;
	private const BOUNDED_TTL = 60;
	private const EXACT_COUNT_KEYS = [
		'malware',
		'wp_files',
		'plugin_files',
		'theme_files',
		'abandoned',
		'vulnerable_assets',
	];

	private ?array $exactRequestCache = null;
	private bool $exactRequestCacheLoaded = false;
	private ?array $boundedRequestCache = null;
	private bool $boundedRequestCacheLoaded = false;

	/**
	 * @return AdminBarExactScanSummary|null
	 */
	public function read() :?array {
		if ( $this->exactRequestCacheLoaded ) {
			return $this->exactRequestCache;
		}

		$this->exactRequestCacheLoaded = true;
		try {
			$cached = \get_transient( $this->exactKey() );
		}
		catch ( \Throwable $e ) {
			$cached = false;
		}

		$summary = \is_array( $cached ) ? $this->normalizeExact( $cached ) : null;
		if ( $summary === null && $cached !== false ) {
			$this->invalidateExact();
		}
		else {
			$this->exactRequestCache = $summary;
		}

		return $this->exactRequestCache;
	}

	/**
	 * @return AdminBarExactScanSummary|null
	 */
	public function refresh( Counts $counts ) :?array {
		try {
			$summary = $this->normalizeExact( $counts->adminBarScanSummary( true ) );
			if ( $summary === null ) {
				$this->invalidateExact();
				return null;
			}

			\set_transient( $this->exactKey(), $summary, self::EXACT_TTL );
			$this->exactRequestCache = $summary;
			$this->exactRequestCacheLoaded = true;
			return $summary;
		}
		catch ( \Throwable $e ) {
			$this->invalidateExact();
			return null;
		}
	}

	/**
	 * @return AdminBarBoundedScanSummary|null
	 */
	public function readBounded() :?array {
		if ( $this->boundedRequestCacheLoaded ) {
			return $this->boundedRequestCache;
		}

		$this->boundedRequestCacheLoaded = true;
		try {
			$cached = \get_transient( $this->boundedKey() );
		}
		catch ( \Throwable $e ) {
			$cached = false;
		}

		$summary = \is_array( $cached ) ? $this->normalizeBounded( $cached ) : null;
		if ( $summary === null && $cached !== false ) {
			$this->invalidateBounded();
		}
		else {
			$this->boundedRequestCache = $summary;
		}

		return $this->boundedRequestCache;
	}

	/**
	 * @return AdminBarBoundedScanSummary|null
	 */
	public function storeBounded( array $summary ) :?array {
		$summary = $this->normalizeBounded( $summary );
		if ( $summary === null ) {
			return null;
		}

		try {
			\set_transient( $this->boundedKey(), $summary, self::BOUNDED_TTL );
		}
		catch ( \Throwable $e ) {
		}

		$this->boundedRequestCache = $summary;
		$this->boundedRequestCacheLoaded = true;
		return $summary;
	}

	public function invalidate() :void {
		$this->invalidateExact();
		$this->invalidateBounded();
	}

	private function invalidateExact() :void {
		$this->exactRequestCache = null;
		$this->exactRequestCacheLoaded = true;
		try {
			\delete_transient( $this->exactKey() );
		}
		catch ( \Throwable $e ) {
		}
	}

	private function invalidateBounded() :void {
		$this->boundedRequestCache = null;
		$this->boundedRequestCacheLoaded = true;
		try {
			\delete_transient( $this->boundedKey() );
		}
		catch ( \Throwable $e ) {
		}
	}

	private function exactKey() :string {
		return self::con()->prefix( 'admin_bar_scan_summary', '_' );
	}

	private function boundedKey() :string {
		return self::con()->prefix( 'admin_bar_scan_summary_bounded', '_' );
	}

	/**
	 * @return AdminBarExactScanSummary|null
	 */
	private function normalizeExact( array $summary ) :?array {
		if ( !isset( $summary[ 'counts' ], $summary[ 'total' ], $summary[ 'is_capped' ] )
			 || !\is_array( $summary[ 'counts' ] )
			 || (bool)$summary[ 'is_capped' ] ) {
			return null;
		}

		$counts = [];
		foreach ( self::EXACT_COUNT_KEYS as $key ) {
			if ( !isset( $summary[ 'counts' ][ $key ] ) || !\is_numeric( $summary[ 'counts' ][ $key ] ) ) {
				return null;
			}
			$counts[ $key ] = \max( 0, (int)$summary[ 'counts' ][ $key ] );
		}

		return [
			'counts'    => $counts,
			'total'     => (int)\array_sum( $counts ),
			'is_capped' => false,
		];
	}

	/**
	 * @return AdminBarBoundedScanSummary|null
	 */
	private function normalizeBounded( array $summary ) :?array {
		if ( !isset( $summary[ 'counts' ], $summary[ 'total' ], $summary[ 'is_capped' ] )
			 || !\is_array( $summary[ 'counts' ] )
			 || $summary[ 'counts' ] !== []
			 || !\is_numeric( $summary[ 'total' ] ) ) {
			return null;
		}

		return [
			'counts'    => [],
			'total'     => \max( 0, (int)$summary[ 'total' ] ),
			'is_capped' => (bool)$summary[ 'is_capped' ],
		];
	}
}
