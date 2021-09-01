<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function processAjaxAction( string $action ) :array {

		switch ( $action ) {
			case 'render_table_traffic':
				$response = $this->ajaxExec_BuildTableTraffic();
				break;

			default:
				$response = parent::processAjaxAction( $action );
		}

		return $response;
	}

	private function ajaxExec_BuildTableTraffic() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return [
			'success' => true,
			'html'    => ( new Shield\Tables\Build\Traffic() )
				->setMod( $mod )
				->setDbHandler( $mod->getDbH_ReqLogs() )
				->render()
		];
	}
}