<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\TrafficTable\BuildTrafficTableData;
use FernleafSystems\Wordpress\Services\Services;

class TrafficLogTableAction extends BaseAction {

	public const SLUG = 'traffictable_action';

	protected function exec() {
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
		$this->response()->action_response_data = $response;
	}

	private function retrieveTableData() :array {
		$builder = ( new BuildTrafficTableData() )->setMod( $this->con()->getModule_Traffic() );
		$builder->table_data = Services::Request()->post( 'table_data', [] );
		return [
			'success'        => true,
			'datatable_data' => $builder->build(),
		];
	}
}