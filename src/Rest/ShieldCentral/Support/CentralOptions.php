<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\PreSetOptSanitize;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * Reads and writes the site's Shield options for ShieldCentral. A write names the catalogue hash it was prepared
 * for and is refused whole when the site's catalogue has changed since.
 */
class CentralOptions {

	use PluginControllerConsumer;

	private CentralOptionsCatalog $catalogue;

	public function __construct() {
		$this->catalogue = new CentralOptionsCatalog();
	}

	/**
	 * The catalogue is sent only when ShieldCentral doesn't already hold this one.
	 */
	public function get( string $knownCatalogueHash ) :array {
		$hash = $this->catalogue->hash();
		return [
			'success'        => true,
			'version'        => self::con()->cfg->version(),
			'catalogue_hash' => $hash,
			'values'         => $this->values(),
			// The options this site's licence doesn't allow.
			'access'         => \array_values( \array_filter(
				$this->catalogue->keys(),
				fn( string $key ) :bool => !self::con()->opts->optHasAccess( $key )
			) ),
			...( \hash_equals( $hash, $knownCatalogueHash ) ? [] : [ 'catalogue' => $this->catalogue->catalogue() ] ),
		];
	}

	/**
	 * Assigns only the options sent. Each is "corrected" when the value stored differs from the one sent, and the
	 * stored values of every option are returned, since Shield's corrections can change options that weren't sent.
	 *
	 * @param array<string,mixed> $options
	 */
	public function set( string $catalogueHash, array $options ) :array {
		$hash = $this->catalogue->hash();
		if ( !\hash_equals( $hash, $catalogueHash ) ) {
			return [
				'success'            => false,
				'message'            => "The site's Shield options have changed since the request was prepared.",
				'diagnostic_code'    => 'catalogue_hash_mismatch',
				'reconnect_required' => false,
				'version'            => self::con()->cfg->version(),
				'catalogue_hash'     => $hash,
			];
		}

		$opts = self::con()->opts;
		$keys = $this->catalogue->keys();
		$results = [];
		$assigned = [];
		foreach ( $options as $key => $value ) {
			$key = (string)$key;
			if ( !\in_array( $key, $keys, true ) ) {
				$results[ $key ] = $this->rejected( 'ShieldCentral cannot set this option.' );
			}
			elseif ( !$opts->optHasAccess( $key ) ) {
				$results[ $key ] = $this->rejected( "This site's licence doesn't include this option." );
			}
			else {
				try {
					$opts->optSet( $key, ( new PreSetOptSanitize( $key, $value ) )->run() );
					$assigned[ $key ] = $value;
				}
				catch ( \Exception $e ) {
					$results[ $key ] = $this->rejected( $e->getMessage() );
				}
			}
		}

		$opts->store();

		$values = $this->values();
		foreach ( $assigned as $key => $value ) {
			$results[ $key ] = [ 'status' => $values[ $key ] === $value ? 'applied' : 'corrected' ];
		}

		return [
			'success'        => true,
			'version'        => self::con()->cfg->version(),
			'catalogue_hash' => $hash,
			'results'        => $results,
			'values'         => $values,
		];
	}

	private function values() :array {
		$values = [];
		foreach ( $this->catalogue->keys() as $key ) {
			$values[ $key ] = self::con()->opts->optGet( $key );
		}
		return $values;
	}

	private function rejected( string $reason ) :array {
		return [
			'status' => 'rejected',
			'reason' => $reason,
		];
	}
}
