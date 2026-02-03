<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\DeleteSession;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Sessions\BuildSessionsTableData;

class SessionsTableAction extends BaseAction {

	public const SLUG = 'sessionstable_action';

	protected function exec() {
		try {
			switch ( $this->action_data[ 'sub_action' ] ) {
				case 'retrieve_table_data':
					$response = $this->retrieveTableData();
					break;
				case 'delete':
					( new DeleteSession() )->byShieldIDs( $this->action_data[ 'rids' ] );
					$response = [
						'success'     => true,
						'page_reload' => false,
						'message'     => __( 'Selected Sessions Deleted.', 'wp-simple-firewall' ),
					];
					break;
				default:
					throw new \Exception( 'Not a supported Sessions table sub_action: '.$this->action_data[ 'sub_action' ] );
			}
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
		$builder = new BuildSessionsTableData();
		$builder->table_data = $this->action_data[ 'table_data' ];
		return [
			'success'        => true,
			'datatable_data' => $builder->build(),
		];
	}
}