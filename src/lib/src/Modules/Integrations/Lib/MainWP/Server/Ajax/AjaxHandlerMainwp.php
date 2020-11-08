<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Ajax;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\MWPSiteVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandlerMainwp extends Shield\Modules\BaseShield\AjaxHandler {

	/**
	 * @var MainWP\Common\MWPSiteVO|null
	 */
	private $site;

	protected function processAjaxAction( string $action ) :array {

		if ( strpos( $action, 'mwp_sh_' ) === 0 ) {
			$siteID = (int)Services::Request()->post( 'sid' );
			if ( empty( $siteID ) ) {
				//TODO - proper ajax response
				throw new \Exception( 'invalid site ID' );
			}

			$this->site = MWPSiteVO::LoadByID( $siteID );
		}

		switch ( $action ) {
			case 'mwp_sh_activate':
				$resp = $this->ajaxExec_Activate();
				break;

			default:
				$resp = parent::processAjaxAction( $action );
		}

		return $resp;
	}

	private function ajaxExec_Activate() :array {
		try {
			( new Server\Actions\AlignPlugin() )
				->setMod( $this->getMod() )
				->setMwpSite( $this->site )
				->activate();
		}
		catch ( \Exception $e ) {
			$msg = $e->getMessage();
			$success = false;
			$response = $msg;
		}

		return [
			'success' => $success,
			'message' => $msg,
			'html'    => $response,
		];
	}

	private function ajaxExec_Deactivate() :array {
		try {
			( new Server\Actions\AlignPlugin() )
				->setMod( $this->getMod() )
				->setMwpSite( $this->site )
				->de();
		}
		catch ( \Exception $e ) {
			$msg = $e->getMessage();
			$success = false;
			$response = $msg;
		}

		return [
			'success' => $success,
			'message' => $msg,
			'html'    => $response,
		];
	}
}