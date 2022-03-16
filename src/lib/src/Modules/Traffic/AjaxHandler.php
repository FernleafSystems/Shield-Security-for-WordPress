<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function processAjaxAction( string $action ) :array {

		switch ( $action ) {

			case 'traffictable_action':
				$response = $this->ajaxExec_TrafficTableAction();
				break;

			default:
				$response = parent::processAjaxAction( $action );
		}

		return $response;
	}

	private function ajaxExec_TrafficTableAction() :array {
		try {
			return ( new Lib\TrafficTable\DelegateAjaxHandler() )
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