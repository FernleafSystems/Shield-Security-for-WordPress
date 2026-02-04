<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Traffic\BuildTrafficTableData;

class TrafficLogTableAction extends BaseAction {

	public const SLUG = 'traffictable_action';

	protected function exec() {
		try {
			$action = $this->action_data[ 'sub_action' ];
			switch ( $action ) {
				case 'retrieve_table_data':
					$response = $this->retrieveTableData();
					break;
				default:
					throw new \Exception( 'Not a supported Traffic Log table sub_action: '.$action );
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
		$builder = new BuildTrafficTableData();
		$builder->table_data = $this->action_data[ 'table_data' ] ?? [];
		return [
			'success'        => true,
			'datatable_data' => $builder->build(),
		];
	}
}