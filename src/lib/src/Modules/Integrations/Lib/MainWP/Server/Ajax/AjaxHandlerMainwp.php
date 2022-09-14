<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Ajax;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandlerMainwp extends Shield\Modules\BaseShield\AjaxHandler {

	protected function getAjaxActionCallbackMap( bool $isAuth ) :array {
		$map = parent::getAjaxActionCallbackMap( $isAuth );
		if ( $isAuth ) {
			$map = array_merge( $map, [
				'ext_table'   => [ $this, 'ajaxExec_ExtensionTableSites' ],
				'site_action' => [ $this, 'ajaxExec_SiteAction' ],
			] );
		}
		return $map;
	}

	public function ajaxExec_SiteAction() :array {
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

	public function ajaxExec_ExtensionTableSites() :array {
		return [];
	}
}