<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class SystemLibOpenssl extends Base {

	public const SLUG = 'system_lib_openssl';
	public const WEIGHT = 2;
	private const MIN_VERSION = '1.1.1';

	private $current;

	private $currentFull;

	protected function hrefFull() :string {
		return 'https://www.openssl.org/news/vulnerabilities.html';
	}

	protected function hrefFullTargetBlank() :bool {
		return true;
	}

	protected function isApplicable() :bool {
		return \function_exists( '\curl_version' ) && \in_array( 'openssl', get_loaded_extensions() );
	}

	protected function testIfProtected() :bool {
		$this->parseVersion();
		return empty( $this->currentFull ) ? false : \version_compare( $this->current, self::MIN_VERSION, '>=' );
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
			__( "We couldn't determine the version of your OpenSSL library.", 'wp-simple-firewall' )
			: sprintf( "Your OpenSSL library is older than '%s' at version '%s,' which is a little old.", self::MIN_VERSION, $this->currentFull );
	}

	public function guidance() :array {
		$this->parseVersion();
		return [
			'checks'   => sprintf(
				/* translators: %s: minimum OpenSSL version */
				__( 'Shield reads the version of the OpenSSL library your server uses for the secure connections it makes, and checks that it is at least %s.', 'wp-simple-firewall' ),
				self::MIN_VERSION ),
			'why'      => [
				__( 'OpenSSL encrypts the connections your site makes to other services, such as payment providers, update servers and email services. Old versions have known weaknesses.', 'wp-simple-firewall' ),
			],
			// With no version read, the server may be using another library for this, and nothing may be wrong.
			'steps'    => empty( $this->currentFull ) ? [
				__( "Shield couldn't read the version, so there may be nothing wrong. Your server may use a different library for its secure connections.", 'wp-simple-firewall' ),
				__( 'Ask your web host which library your server uses for them, and whether it is up to date.', 'wp-simple-firewall' ),
			] : [
				__( "Ask your web host to update the OpenSSL library on your server. It's part of the server's own software and can't be changed from WordPress.", 'wp-simple-firewall' ),
				__( "If they can't, ask whether a newer hosting plan or server would bring it up to date.", 'wp-simple-firewall' ),
			],
			'who'      => __( 'Your web host.', 'wp-simple-firewall' ),
			'practice' => [
				__( 'Choose hosting that keeps its server software up to date.', 'wp-simple-firewall' ),
			],
		];
	}

	private function parseVersion() {
		if ( !isset( $this->current ) ) {
			$this->current = '';
			$this->currentFull = '';

			$curlVersion = \function_exists( '\curl_version' ) ? \curl_version() : null;
			if ( \is_array( $curlVersion ) && \is_string( $curlVersion[ 'ssl_version' ] ?? '' )
				 && \preg_match( '#^OpenSSL/([\d.]+).*$#', \trim( $curlVersion[ 'ssl_version' ] ), $matches ) ) {
				$this->current = $matches[ 1 ];
				$this->currentFull = $curlVersion[ 'ssl_version' ];
			}
		}
	}
}
