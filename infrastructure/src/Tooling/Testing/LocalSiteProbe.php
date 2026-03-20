<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

class LocalSiteProbe {

	public function isHttpReady( string $url ) :bool {
		$curl = \curl_init( $url );
		if ( $curl === false ) {
			return false;
		}

		\curl_setopt_array( $curl, [
			\CURLOPT_NOBODY => true,
			\CURLOPT_FOLLOWLOCATION => true,
			\CURLOPT_RETURNTRANSFER => true,
			\CURLOPT_TIMEOUT => 15,
		] );

		\curl_exec( $curl );
		$statusCode = (int)\curl_getinfo( $curl, \CURLINFO_RESPONSE_CODE );
		\curl_close( $curl );

		return $statusCode > 0 && $statusCode < 500;
	}

	public function waitForHttpReady( string $url, int $timeoutSeconds = 60 ) :bool {
		$startedAt = \time();
		while ( ( \time() - $startedAt ) < $timeoutSeconds ) {
			if ( $this->isHttpReady( $url ) ) {
				return true;
			}
			\usleep( 500000 );
		}
		return false;
	}

	public function isTcpPortOpen( string $host, int $port ) :bool {
		$socket = @\fsockopen( $host, $port, $errno, $error, 1.0 );
		if ( !\is_resource( $socket ) ) {
			return false;
		}
		\fclose( $socket );
		return true;
	}
}
