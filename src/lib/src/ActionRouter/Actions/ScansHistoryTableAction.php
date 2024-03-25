<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\SecurityRules\BuildSecurityRulesTableData;

class ScansHistoryTableAction extends BaseAction {

	public const SLUG = 'scans_history_table_action';

	protected function exec() {
		$con = self::con();

		$response = [
			'success'     => false,
			'page_reload' => false,
			'message'     => '',
		];

		try {
			$action = $this->action_data[ 'sub_action' ] ?? '';
			switch ( $action ) {
				case 'retrieve_table_data':
					$response = $this->retrieveTableData();
					break;
			}

			$con->rules->buildAndStore();
		}
		catch ( \Exception $e ) {
			$response = [
				'success'     => false,
				'page_reload' => false,
				'message'     => $e->getMessage(),
			];
		}

		$this->response()->action_response_data = $response;
	}

	private function retrieveTableData() :array {
		$builder = new BuildSecurityRulesTableData();
		$builder->table_data = $this->action_data[ 'table_data' ];
		return [
			'success'        => true,
			'datatable_data' => $builder->build(),
		];
	}
}