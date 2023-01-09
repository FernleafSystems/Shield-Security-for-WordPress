<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

class SystemLibOpenssl extends Base {

	public const SLUG = 'system_lib_openssl';
	public const WEIGHT = 20;
	private const MIN_VERSION = '1.1.0';

	private $current;

	private $currentFull;

	protected function isProtected() :bool {
		$this->parseVersion();
		return empty( $this->currentFull ) ? false : version_compare( $this->current, self::MIN_VERSION, '>=' );
	}

	public function title() :string {
		return __( 'OpenSSL Extension', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return sprintf( "OpenSSL library version is '%s', which is at least version '%s'.", $this->currentFull, self::MIN_VERSION );
	}

	public function descUnprotected() :string {
		$this->parseVersion();
		return empty( $this->currentFull ) ?
			__( "We couldn't determine the version of your OpenSSL library." )
			: sprintf( "Your OpenSSL library is older than '%s' at version '%s,' which is a little old.", self::MIN_VERSION, $this->currentFull );
	}

	private function parseVersion() {
		if ( !isset( $this->current ) ) {
			$this->current = '';
			$this->currentFull = '';

			$curlVersion = function_exists( '\curl_version' ) ? \curl_version() : null;
			if ( is_array( $curlVersion ) && is_string( $curlVersion[ 'ssl_version' ] ?? '' )
				 && preg_match( '#^OpenSSL/([\d.]+).*$#', trim( $curlVersion[ 'ssl_version' ] ), $matches ) ) {
				$this->current = $matches[ 1 ];
				$this->currentFull = $curlVersion[ 'ssl_version' ];
			}
		}
	}
}