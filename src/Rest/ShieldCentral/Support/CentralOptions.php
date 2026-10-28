<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\PreSetOptSanitize;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\OptsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * Reads and writes the site's Shield options for ShieldCentral. A write names the catalogue hash it was prepared
 * for and is refused whole when the site's catalogue has changed since.
 *
 * A password option (the Security Admin PIN) is write-only: Central is told only whether it holds a value, and sets it
 * only to a bcrypt hash it made of the PIN, which is stored as it is. Shield checks a PIN against that hash as it does
 * against its own, and its import/export copies the stored hash between sites the same way.
 */
class CentralOptions {

	use PluginControllerConsumer;

	/**
	 * A bcrypt hash as PHP's password_hash() makes it, with a cost crypt() takes: wp_check_password() checks a PIN
	 * against it, so a PIN can't be set to a hash no PIN matches.
	 */
	private const BCRYPT_HASH = '#\A\$2y\$(0[4-9]|[12][0-9]|3[01])\$[./A-Za-z0-9]{53}\z#';

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
			'values'              => $this->values( $this->stored() ),
			'unavailable_options' => \array_values( \array_filter(
				$this->catalogue->keys(),
				fn( string $key ) :bool => !self::con()->opts->optHasAccess( $key )
			) ),
			...( \hash_equals( $hash, $knownCatalogueHash ) ? [] : [ 'catalogue' => $this->catalogue->catalogue() ] ),
		];
	}

	/**
	 * Assigns only the options sent. Each is "applied" when the value stored is the one sent, "rejected" when the
	 * stored value is still the one from before the write although Shield's sanitising made it another, and "corrected"
	 * otherwise, as when sanitising trims a value to the one already stored. The stored values of every option are
	 * returned, since Shield's corrections can change options that weren't sent. A password option takes only a bcrypt
	 * hash, so Central can't empty it.
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
		$before = $this->stored();
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
			elseif ( $opts->optType( $key ) === 'password' && !( \is_string( $value ) && \preg_match( self::BCRYPT_HASH, $value ) ) ) {
				$results[ $key ] = $this->rejected( "This must be a bcrypt hash of the new value. ShieldCentral can't empty it." );
			}
			else {
				try {
					$sanitised = ( new PreSetOptSanitize( $key, $value ) )->run();
					$opts->optSet( $key, $sanitised );
					$assigned[ $key ] = [ $value, $sanitised ];
				}
				catch ( \Exception $e ) {
					$results[ $key ] = $this->rejected( $e->getMessage() );
				}
			}
		}

		$opts->store();

		$stored = $this->stored();
		foreach ( $assigned as $key => [ $value, $sanitised ] ) {
			if ( $stored[ $key ] === $value ) {
				$results[ $key ] = [ 'status' => 'applied' ];
			}
			elseif ( $stored[ $key ] === $before[ $key ] && $sanitised !== $before[ $key ] ) {
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
			'values'         => $this->values( $stored ),
		];
	}

	/**
	 * Each option's stored value, read through a new handler, which loads the stored option rather than the plugin
	 * handler's in-memory copy: that copy keeps a write WordPress didn't save.
	 */
	private function stored() :array {
		$handler = new OptsHandler();
		$stored = [];
		foreach ( $this->catalogue->keys() as $key ) {
			$stored[ $key ] = $handler->optGet( $key );
		}
		return $stored;
	}

	/**
	 * The stored values as Central is sent them: a password option only as whether it holds a value.
	 */
	private function values( array $stored ) :array {
		foreach ( $stored as $key => $value ) {
			if ( self::con()->opts->optType( $key ) === 'password' ) {
				$stored[ $key ] = (string)$value !== '';
			}
		}
		return $stored;
	}

	private function rejected( string $reason ) :array {
		return [
			'status' => 'rejected',
			'reason' => $reason,
		];
	}
}
