<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites;

class ScopedTargetHostRequest {

	/**
	 * @return mixed
	 */
	public function run( string $targetUrl, callable $request ) {
		$targetHost = (string)( \wp_parse_url( $targetUrl, \PHP_URL_HOST ) ?: '' );
		$allowTargetHost = static fn( $external, $host ) :bool => (
			$targetHost !== '' && \strcasecmp( (string)$host, $targetHost ) === 0
		) || (bool)$external;

		add_filter( 'http_request_host_is_external', $allowTargetHost, 11, 2 );
		try {
			return $request();
		}
		finally {
			remove_filter( 'http_request_host_is_external', $allowTargetHost, 11 );
		}
	}
}
