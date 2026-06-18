<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportExport_UpdateNotified;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class PingSender {

	use PluginControllerConsumer;

	/**
	 * @return array{success:bool,http_code:int,error:string}
	 */
	public function send( string $url, int $timeout = 5, string $importID = '' ) :array {
		$url = Services::Data()->validateSimpleHttpUrl( $url );
		if ( $url === false ) {
			return self::result( false, 0, 'invalid_url' );
		}

		$masterUrl = $this->canonicalMasterUrl();
		$aux = empty( $masterUrl ) ? [] : [ 'master_url' => $masterUrl ];
		if ( $importID !== '' ) {
			$aux[ 'id' ] = $importID;
		}
		$targetUrl = self::con()->plugin_urls->noncedPluginAction(
			PluginImportExport_UpdateNotified::class,
			(string)$url,
			$aux
		);
		return ( new ScopedTargetHostRequest() )->run( $targetUrl, static function () use ( $targetUrl, $timeout ) :array {
			$http = Services::HttpRequest();
			$http->get( $targetUrl, [
				'timeout' => $timeout,
			] );
			$code = $http->lastResponse ? (int)$http->lastResponse->getCode() : 0;
			return self::result( true, $code, '' );
		} );
	}

	private function canonicalMasterUrl() :string {
		$masterUrl = Services::Data()->validateSimpleHttpUrl( Services::WpGeneral()->getHomeUrl() );
		return $masterUrl === false ? '' : (string)$masterUrl;
	}

	/**
	 * @return array{success:bool,http_code:int,error:string}
	 */
	private static function result( bool $success, int $httpCode, string $error ) :array {
		return [
			'success'   => $success,
			'http_code' => $httpCode,
			'error'     => $error,
		];
	}
}
