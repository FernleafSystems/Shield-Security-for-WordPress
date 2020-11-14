<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Ajax;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandlerMainwp extends Shield\Modules\BaseShield\AjaxHandler {

	protected function processAjaxAction( string $action ) :array {
		$resp = [];

		// This allows us to provide a specific MainWP error message
		if ( strpos( $action, 'mwp_' ) === 0 ) {

			switch ( $action ) {
				case 'mwp_sh_ext_table':
					$resp = $this->ajaxExec_SiteAction();
					break;

				case 'mwp_sh_site_action':
					$resp = $this->ajaxExec_ExtensionTableSites();
					break;

				default:
					$resp = [
						'success' => false,
						'message' => sprintf( __( 'Not a supported MainWP+%s action.' ),
							$this->getCon()->getHumanName() )
					];
			}
		}

		return $resp;
	}

	private function ajaxExec_SiteAction() :array {
		$req = Services::Request();

		$siteID = (int)$req->post( 'sid' );
		$action = $req->post( 'saction' );
		try {
			if ( empty( $siteID ) ) {
				throw new \Exception( 'invalid site ID' );
			}
			$resp = ( new PerformSiteAction() )
				->setMwpSite( MainWP\Common\MWPSiteVO::LoadByID( $siteID ) )
				->setMod( $this->getMod() )
				->run( $action );
		}
		catch ( \Exception $e ) {
			$resp = [
				'success' => false,
				'message' => $e->getMessage()
			];
		}

		return $resp;
	}

	private function ajaxExec_ExtensionTableSites() {

	}
}