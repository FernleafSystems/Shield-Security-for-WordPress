<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportExport_UpdateNotified;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class PingSender {

	use PluginControllerConsumer;

	private SyncSiteUrlValidator $urlValidator;

	public function __construct( ?SyncSiteUrlValidator $urlValidator = null ) {
		$this->urlValidator = $urlValidator ?? new SyncSiteUrlValidator();
	}

	/**
	 * @return array{success:bool,http_code:int,error:string}
	 */
	public function send( string $url, int $timeout = 5, string $importID = '' ) :array {
		try {
			$url = $this->urlValidator->validateTrustedSyncUrl( $url );
		}
		catch ( \InvalidArgumentException $e ) {
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
				'timeout'            => $timeout,
				'reject_unsafe_urls' => true,
			] );
			$code = $http->lastResponse ? (int)$http->lastResponse->getCode() : 0;
			return self::result( true, $code, '' );
		} );
	}

	private function canonicalMasterUrl() :string {
		return $this->urlValidator->canonicalize( Services::WpGeneral()->getHomeUrl() );
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
