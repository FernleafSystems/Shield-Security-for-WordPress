<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @phpstan-import-type AdminBarExactScanSummary from Counts
 */
class AdminBarScanSummaryCache {

	use PluginControllerConsumer;

	private const TTL = 600;
	private const EXACT_COUNT_KEYS = [
		'malware',
		'wp_files',
		'plugin_files',
		'theme_files',
		'abandoned',
		'vulnerable_assets',
	];

	private ?array $requestCache = null;
	private bool $requestCacheLoaded = false;

	/**
	 * @return AdminBarExactScanSummary|null
	 */
	public function read() :?array {
		if ( $this->requestCacheLoaded ) {
			return $this->requestCache;
		}

		$this->requestCacheLoaded = true;
		try {
			$cached = \get_transient( $this->key() );
		}
		catch ( \Throwable $e ) {
			$cached = false;
		}

		$summary = \is_array( $cached ) ? $this->normalize( $cached ) : null;
		if ( $summary === null && $cached !== false ) {
			$this->invalidate();
		}
		else {
			$this->requestCache = $summary;
		}

		return $this->requestCache;
	}

	/**
	 * @return AdminBarExactScanSummary|null
	 */
	public function refresh( Counts $counts ) :?array {
		try {
			$summary = $this->normalize( $counts->adminBarScanSummary( true ) );
			if ( $summary === null ) {
				$this->invalidate();
				return null;
			}

			\set_transient( $this->key(), $summary, self::TTL );
			$this->requestCache = $summary;
			$this->requestCacheLoaded = true;
			return $summary;
		}
		catch ( \Throwable $e ) {
			$this->invalidate();
			return null;
		}
	}

	public function invalidate() :void {
		$this->requestCache = null;
		$this->requestCacheLoaded = true;
		try {
			\delete_transient( $this->key() );
		}
		catch ( \Throwable $e ) {
		}
	}

	private function key() :string {
		return self::con()->prefix( 'admin_bar_scan_summary', '_' );
	}

	/**
	 * @return AdminBarExactScanSummary|null
	 */
	private function normalize( array $summary ) :?array {
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
}
