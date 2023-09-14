<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\ServerActions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\MainwpBase;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;

class MainwpServerClientActionHandler extends MainwpBase {

	public const SLUG = 'mwp_server_site_client_action_handler';

	protected function exec() {
		try {
			$response = $this->fireActionOnClientSite();
		}
		catch ( \Exception $e ) {
			$response = [
				'success' => false,
				'message' => $e->getMessage()
			];
		}
		$this->response()->action_response_data = $response;
	}

	/**
	 * @throws ActionException
	 */
	protected function fireActionOnClientSite() :array {
		$clientSiteActionData = $this->action_data[ 'client_site_action_data' ];

		$actionParams = $clientSiteActionData[ 'site_action_params' ] ?? [];
		$actionParams[ 'client_site_id' ] = $this->action_data[ 'client_site_id' ];

		return self::con()->action_router
			->action( $clientSiteActionData[ 'site_action_slug' ], $actionParams )
			->action_response_data;
	}

	protected function getRequiredDataKeys() :array {
		return [
			'client_site_id',
			'client_site_action_data',
		];
	}
}