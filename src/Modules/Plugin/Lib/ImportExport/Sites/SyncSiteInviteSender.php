<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportExport_NetworkInviteRequest;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class SyncSiteInviteSender {

	use PluginControllerConsumer;

	/**
	 * @return array{success:bool,http_code:int,error:string}
	 */
	public function send( string $clientUrl, int $timeout = 2 ) :array {
		$http = Services::HttpRequest();
		try {
			$validator = new SyncSiteUrlValidator();
			$clientUrl = $validator->validatePublicOutbound( $clientUrl );
			$targetUrl = self::con()->plugin_urls->noncedPluginAction(
				PluginImportExport_NetworkInviteRequest::class,
				$clientUrl
			);
			$masterUrl = $validator->validatePublicOutbound( Services::WpGeneral()->getHomeUrl(), false );
			$success = $http->post( $targetUrl, [
				'timeout'            => $timeout,
				'redirection'        => 1,
				'reject_unsafe_urls' => true,
				'body'               => [
					'master_url' => $masterUrl,
				],
			] );
			$code = $http->lastResponse ? (int)$http->lastResponse->getCode() : 0;
			$error = $success ? '' : ( $http->lastError ? $http->lastError->get_error_message() : 'invite_request_failed' );
		}
		catch ( \Throwable $e ) {
			$success = false;
			$code = 0;
			$error = $e->getMessage();
		}

		return [
			'success'   => $success,
			'http_code' => $code,
			'error'     => $error,
		];
	}
}
