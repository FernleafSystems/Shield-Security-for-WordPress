<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\MWPSiteVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Ajax\PerformSiteAction;
use FernleafSystems\Wordpress\Services\Services;

class MainwpSiteAction extends MainwpBase {

	const SLUG = 'mainwp_site_action';

	protected function exec() {
		$req = Services::Request();

		$siteID = (int)$req->post( 'sid' );
		$action = $req->post( 'saction' );
		try {
			if ( empty( $siteID ) ) {
				throw new \Exception( 'invalid site ID' );
			}
			$resp = ( new PerformSiteAction() )
				->setMwpSite( MWPSiteVO::LoadByID( $siteID ) )
				->setMod( $this->getMod() )
				->run( $action );
		}
		catch ( \Exception $e ) {
			$resp = [
				'success' => false,
				'message' => $e->getMessage()
			];
		}

		$this->response()->action_response_data = $resp;
	}
}