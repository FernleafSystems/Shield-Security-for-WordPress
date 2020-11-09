<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Ajax;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandlerMainwp extends Shield\Modules\BaseShield\AjaxHandler {

	protected function processAjaxAction( string $action ) :array {
		$req = Services::Request();

		// This allows us to provide a specific MainWP error message
		if ( strpos( $action, 'mwp_' ) === 0 ) {

			if ( $action === 'mwp_sh_site_action' ) {
				$action = $req->post( 'saction' );
			}

			switch ( $action ) {
				case 'activate':
				case 'deactivate':
				case 'sync':
				case 'license':
					$siteID = (int)$req->post( 'sid' );
					try {
						if ( empty( $siteID ) ) {
							throw new \Exception( 'invalid site ID' );
						}
						$site = MainWP\Common\MWPSiteVO::LoadByID( $siteID );
						$resp = ( new PerformSiteAction() )
							->setMwpSite( $site )
							->setMod( $this->getMod() )
							->run( $action );
					}
					catch ( \Exception $e ) {
						$resp = [
							'success' => false,
							'message' => $e->getMessage()
						];
					}
					break;

				default:
					$resp = [
						'success' => false,
						'message' => sprintf( __( 'Not a supported MainWP+%s action.' ),
							$this->getCon()->getHumanName() )
					];
			}
		}
		else {
			$resp = [];
		}

		return $resp;
	}
}