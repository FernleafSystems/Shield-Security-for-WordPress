<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\ServerActions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\MainwpBase;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\MWPSiteVO;
use MainWP\Dashboard\MainWP_Connect;

class MainwpServerSiteActionHandler extends MainwpBase {

	public const SLUG = 'mwp_server_site_action_handler';

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
		$con = $this->getCon();

		try {
			$result = $con->action_router
				->action( $this->action_data[ 'site_action_slug' ], $this->action_data )
				->action_response_data;
		}
		catch ( ActionException $ae ) {
			$info = MainWP_Connect::fetch_url_authed(
				$this->loadMwpSite()->siteobj,
				'extra_execution',
				[
					$con->prefix( 'mwp-action' ) => $this->action_data[ 'site_action_slug' ],
					$con->prefix( 'mwp-params' ) => $this->action_data[ 'site_action_params' ] ?? []
				]
			);

			$key = $con->prefix( 'mwp-action-response' );
			if ( empty( $info ) || !is_array( $info ) || !isset( $info[ $key ] ) ) {
				throw new ActionException( 'Empty response from Shield client site' );
			}

			$result = json_decode( $info[ $key ], true );
			if ( empty( $result ) || !is_array( $result ) ) {
				throw new ActionException( 'Invalid response from Shield client site' );
			}
		}

		return $result;
	}

	protected function loadMwpSite() :MWPSiteVO {
		return MWPSiteVO::LoadByID( (int)$this->action_data[ 'site_id' ] );
	}

	protected function getRequiredDataKeys() :array {
		return [
			'site_id',
			'site_action_slug',
		];
	}
}