<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\MWPSiteVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Ajax\PerformSiteAction;
use MainWP\Dashboard\MainWP_Connect;

class MainwpServerSiteAction extends MainwpBase {

	public const SLUG = 'mwp_server_site_action';

	protected function exec() {
		$siteID = (int)$this->action_data[ 'site_id' ];
		try {
			if ( empty( $siteID ) ) {
				throw new \Exception( 'Invalid site ID' );
			}
			try {
				$response = $this->fireActionOnClientSite();
			}
			catch ( \Exception $e ) {
				$response = ( new PerformSiteAction() )
					->setMwpSite( MWPSiteVO::LoadByID( $siteID ) )
					->setMod( $this->getMod() )
					->run(
						$this->action_data[ 'site_action' ],
						$this->action_data[ 'site_action_params' ] ?? []
					);
			}
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
		$info = MainWP_Connect::fetch_url_authed(
			$this->loadMwpSite()->siteobj,
			'extra_execution',
			[
				$this->getCon()->prefix( 'mwp-action' ) => $this->action_data[ 'site_action_slug' ],
				$this->getCon()->prefix( 'mwp-params' ) => $this->action_data[ 'site_action_params' ] ?? []
			]
		);

		$key = $this->getCon()->prefix( 'mwp-action-response' );
		if ( empty( $info ) || !is_array( $info ) || !isset( $info[ $key ] ) ) {
			throw new ActionException( 'Empty response from Shield client site' );
		}

		$decoded = json_decode( $info[ $key ], true );
		if ( empty( $decoded ) || !is_array( $decoded ) ) {
			throw new ActionException( 'Invalid response from Shield client site' );
		}

		return $decoded;
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