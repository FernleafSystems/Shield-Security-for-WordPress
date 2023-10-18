<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Sessions\Table\BuildSessionsTableData;

class SessionsTableAction extends BaseAction {

	public const SLUG = 'sessionstable_action';

	protected function exec() {
		try {
			switch ( $this->action_data[ 'sub_action' ] ) {
				case 'retrieve_table_data':
					$response = $this->retrieveTableData();
					break;
				default:
					throw new \Exception( 'Not a supported Activity Log table sub_action: '.$this->action_data[ 'sub_action' ] );
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
		$builder = new BuildSessionsTableData();
		$builder->table_data = $this->action_data[ 'table_data' ];
		return [
			'success'        => true,
			'datatable_data' => $builder->build(),
		];
	}
}