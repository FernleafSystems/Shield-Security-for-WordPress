<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Sites;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportExport_UpdateNotified;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Diagnostics\{
	HttpOutcome,
	SyncObservation
};
use FernleafSystems\Wordpress\Services\Services;

class PingSender {

	use PluginControllerConsumer;

	private SyncSiteUrlValidator $urlValidator;

	public function __construct( ?SyncSiteUrlValidator $urlValidator = null ) {
		$this->urlValidator = $urlValidator ?? new SyncSiteUrlValidator();
	}

	/**
	 * @return array{success:bool,http_code:int,error:string,observation:?array}
	 */
	public function send( string $url, int $timeout = 5, string $importID = '' ) :array {
		try {
			$url = $this->urlValidator->validateTrustedSyncUrl( $url );
		}
		catch ( \InvalidArgumentException $e ) {
			return self::result( false, 0, 'invalid_url', self::observation(
				SyncObservation::RESULT_LOCAL_TARGET_VALIDATION_FAILED
			) );
		}

		$masterUrl = $this->canonicalMasterUrl();
		$aux = empty( $masterUrl ) ? [] : [ 'master_url' => $masterUrl ];
		if ( $importID !== '' ) {
			$aux[ 'id' ] = $importID;
		}
		$targetUrl = self::con()->plugin_urls->noncedPluginAction(
			PluginImportExport_UpdateNotified::class,
			$url,
			$aux
		);
		return ( new ScopedTargetHostRequest() )->run( $targetUrl, static function () use ( $targetUrl, $timeout ) :array {
			$http = Services::HttpRequest();
			$http->get( $targetUrl, [
				'timeout'            => $timeout,
				'reject_unsafe_urls' => true,
			] );
			$outcome = HttpOutcome::fromRequest( '', $http );
			return self::result(
				true,
				$outcome->status() ?? 0,
				'',
				self::observation(
					$outcome->hasResponse()
						? SyncObservation::RESULT_HTTP_RESPONSE_RECEIVED
						: SyncObservation::RESULT_NO_HTTP_RESPONSE,
					$outcome->observationFields()
				)
			);
		} );
	}

	private function canonicalMasterUrl() :string {
		return $this->urlValidator->canonicalize( Services::WpGeneral()->getHomeUrl() );
	}

	/**
	 * @return array{success:bool,http_code:int,error:string,observation:?array}
	 */
	private static function result( bool $success, int $httpCode, string $error, ?array $observation ) :array {
		return [
			'success'   => $success,
			'http_code' => $httpCode,
			'error'     => $error,
			'observation' => $observation,
		];
	}

	private static function observation( string $result, array $optional = [] ) :?array {
		return SyncObservation::create(
			Services::Request()->ts(),
			SyncObservation::PHASE_NOTIFICATION,
			$result,
			SyncObservation::VERIFICATION_NOT_APPLICABLE,
			$optional
		);
	}
}
