<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function processAjaxAction( string $action ) :array {

		switch ( $action ) {
			case 'logtable_action':
				$response = $this->ajaxExec_AuditTrailTableAction();
				break;
			default:
				$response = parent::processAjaxAction( $action );
		}

		return $response;
	}

	private function ajaxExec_AuditTrailTableAction() :array {
		try {
			return ( new Lib\LogTable\DelegateAjaxHandler() )
				->setMod( $this->getMod() )
				->processAjaxAction();
		}
		catch ( \Exception $e ) {
			return [
				'success'     => false,
				'page_reload' => true,
				'message'     => $e->getMessage(),
			];
		}
	}
}