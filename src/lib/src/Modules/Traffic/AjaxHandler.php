<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\TrafficTable\BuildTrafficTableData;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function getAjaxActionCallbackMap( bool $isAuth ) :array {
		$map = parent::getAjaxActionCallbackMap( $isAuth );
		if ( $isAuth ) {
			$map = array_merge( $map, [
				'traffictable_action' => [ $this, 'ajaxExec_TrafficTableAction' ],
			] );
		}
		return $map;
	}

	public function ajaxExec_TrafficTableAction() :array {
		try {
			$action = Services::Request()->post( 'sub_action' );
			switch ( $action ) {

				case 'retrieve_table_data':
					$response = $this->retrieveTableData();
					break;

				default:
					throw new \Exception( 'Not a supported Activity Log table sub_action: '.$action );
			}
		}
		catch ( \Exception $e ) {
			$response = [
				'success'     => false,
				'page_reload' => true,
				'message'     => $e->getMessage(),
			];
		}

		return $response;
	}

	private function retrieveTableData() :array {
		$builder = ( new BuildTrafficTableData() )->setMod( $this->getMod() );
		$builder->table_data = Services::Request()->post( 'table_data', [] );
		return [
			'success'        => true,
			'datatable_data' => $builder->build(),
		];
	}
}