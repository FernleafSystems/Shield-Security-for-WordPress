<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\PreSetOptSanitize;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\OptsHandler;
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
			'success'             => true,
			'version'             => self::con()->cfg->version(),
			'catalogue_hash'      => $hash,
			'values'              => $this->values(),
			'unavailable_options' => \array_values( \array_filter(
				$this->catalogue->keys(),
				fn( string $key ) :bool => !self::con()->opts->optHasAccess( $key )
			) ),
			...( \hash_equals( $hash, $knownCatalogueHash ) ? [] : [ 'catalogue' => $this->catalogue->catalogue() ] ),
		];
	}

	/**
	 * Assigns only the options sent. Each is "applied" when the value stored is the one sent, "rejected" when the
	 * stored value is still the one from before the write, and "corrected" otherwise. The stored values of every
	 * option are returned, since Shield's corrections can change options that weren't sent.
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
		$before = $this->values();
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
			elseif ( $opts->optType( $key ) === 'array' && \is_array( $value )
					 && \array_filter( $value, fn( $entry ) :bool => !\is_string( $entry ) ) !== [] ) {
				// Array options hold strings. PreSetOptSanitize checks a multiple select's entries, but only that an
				// array option is an array, and Shield's corrections fail on an entry that isn't a string.
				$results[ $key ] = $this->rejected( 'Every entry in the list must be text.' );
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
			if ( $values[ $key ] === $value ) {
				$results[ $key ] = [ 'status' => 'applied' ];
			}
			elseif ( $values[ $key ] === $before[ $key ] ) {
				$results[ $key ] = $this->rejected( 'Not saved.' );
			}
			else {
				$results[ $key ] = [ 'status' => 'corrected' ];
			}
		}

		return [
			'success'        => true,
			'version'        => self::con()->cfg->version(),
			'catalogue_hash' => $hash,
			'results'        => $results,
			'values'         => $values,
		];
	}

	/**
	 * Read through a new handler, which loads the stored option rather than the plugin handler's in-memory copy: that
	 * copy keeps a write WordPress didn't save.
	 */
	private function values() :array {
		$stored = new OptsHandler();
		$values = [];
		foreach ( $this->catalogue->keys() as $key ) {
			$values[ $key ] = $stored->optGet( $key );
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
