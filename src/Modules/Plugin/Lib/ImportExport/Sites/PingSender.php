<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportExport_UpdateNotified;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class PingSender {

	use PluginControllerConsumer;

	public function send( string $url, int $timeout = 2 ) :array {
		$targetUrl = self::con()->plugin_urls->noncedPluginAction( PluginImportExport_UpdateNotified::class, $url );
		$targetHost = (string)( \wp_parse_url( $targetUrl, \PHP_URL_HOST ) ?: '' );
		$allowTargetHost = static fn( $external, $host ) :bool => ( $targetHost !== '' && \strcasecmp( (string)$host, $targetHost ) === 0 ) || $external;

		add_filter( 'http_request_host_is_external', $allowTargetHost, 11, 2 );
		try {
			$http = Services::HttpRequest();
			$success = $http->get( $targetUrl, [
				'timeout' => $timeout,
			] );
			$code = $http->lastResponse ? (int)$http->lastResponse->getCode() : 0;
			$error = $success ? '' : ( $http->lastError ? $http->lastError->get_error_message() : 'ping_failed' );
		}
		finally {
			remove_filter( 'http_request_host_is_external', $allowTargetHost, 11 );
		}

		return [
			'success'   => $success,
			'http_code' => $code,
			'error'     => $error,
		];
	}
}
