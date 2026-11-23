<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * Keeps the results of site checks that cost too much to work out on every page load.
 *
 * Every entry is stored with a fingerprint of the input it was worked out from, and is worked out again
 * whenever that input changes. The fingerprint is a keyed hash, so the input itself (a password, say) is
 * never stored. All entries share one transient, read once per request.
 */
class SiteQueryCache {

	use PluginControllerConsumer;

	private const TTL = \WEEK_IN_SECONDS;

	/**
	 * @var array<string,array{fingerprint:string,value:mixed}>|null
	 */
	private ?array $entries = null;

	/**
	 * @template T
	 * @param string        $key    names the entry
	 * @param string        $source the input the value is worked out from
	 * @param callable():T  $producer
	 * @return T
	 */
	public function remember( string $key, string $source, callable $producer ) {
		$fingerprint = $this->fingerprint( $source );
		$entry = $this->entries()[ $key ] ?? null;
		if ( $entry !== null && \hash_equals( $entry[ 'fingerprint' ], $fingerprint ) ) {
			return $entry[ 'value' ];
		}

		$value = $producer();
		$this->entries[ $key ] = [
			'fingerprint' => $fingerprint,
			'value'       => $value,
		];
		try {
			\set_transient( $this->transientKey(), $this->entries, self::TTL );
		}
		catch ( \Throwable $e ) {
		}
		return $value;
	}

	/**
	 * @return array<string,array{fingerprint:string,value:mixed}>
	 */
	private function entries() :array {
		if ( $this->entries === null ) {
			try {
				$stored = \get_transient( $this->transientKey() );
			}
			catch ( \Throwable $e ) {
				$stored = false;
			}

			$this->entries = [];
			if ( \is_array( $stored ) ) {
				foreach ( $stored as $key => $entry ) {
					if ( \is_string( $key ) && \is_array( $entry )
						 && \is_string( $entry[ 'fingerprint' ] ?? null ) && \array_key_exists( 'value', $entry ) ) {
						$this->entries[ $key ] = $entry;
					}
				}
			}
		}
		return $this->entries;
	}

	private function fingerprint( string $source ) :string {
		return \hash_hmac( 'sha256', $source, \wp_salt( 'auth' ) );
	}

	private function transientKey() :string {
		return self::con()->prefix( 'site_query_cache', '_' );
	}
}
