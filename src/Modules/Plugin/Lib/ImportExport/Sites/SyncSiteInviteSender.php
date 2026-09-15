<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportExport_NetworkInviteRequest;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class SyncSiteInviteSender {

	use PluginControllerConsumer;

	/**
	 * @return array{result:string,http_status:int}
	 */
	public function send( string $clientUrl, int $timeout = 2 ) :array {
		$http = Services::HttpRequest();
		try {
			$validator = new SyncSiteUrlValidator();
			$clientUrl = $validator->validateTrustedSyncUrl( $clientUrl );
			$masterUrl = $validator->validateTrustedSyncUrl( Services::WpGeneral()->getHomeUrl(), false );
		}
		catch ( \InvalidArgumentException $e ) {
			return [
				'result'      => InvitationMetadata::RESULT_URL_VALIDATION_FAILURE,
				'http_status' => 0,
			];
		}

		try {
			$targetUrl = self::con()->plugin_urls->noncedPluginAction(
				PluginImportExport_NetworkInviteRequest::class,
				$clientUrl
			);
			( new ScopedTargetHostRequest() )->run(
				$targetUrl,
				static fn() :bool => $http->post( $targetUrl, [
					'timeout'            => $timeout,
					'redirection'        => 1,
					'reject_unsafe_urls' => true,
					'body'               => [
						'master_url' => $masterUrl,
					],
				] )
			);
			$code = $http->lastResponse ? (int)$http->lastResponse->getCode() : 0;
			$result = $code >= 200 && $code < 300
				? InvitationMetadata::RESULT_HTTP_RESPONSE
				: ( $code > 0 ? InvitationMetadata::RESULT_HTTP_FAILURE : InvitationMetadata::RESULT_TRANSPORT_FAILURE );
		}
		catch ( \Throwable $e ) {
			$code = 0;
			$result = InvitationMetadata::RESULT_SENDER_FAILURE;
		}

		return [
			'result'      => $result,
			'http_status' => $code,
		];
	}
}
