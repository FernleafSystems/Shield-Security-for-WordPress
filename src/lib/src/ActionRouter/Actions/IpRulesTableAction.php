<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\IpRules\BuildIpRulesTableData;

class IpRulesTableAction extends BaseAction {

	public const SLUG = 'iprulestable_action';

	protected function exec() {
		$resp = $this->response();
		try {
			$action = $this->action_data[ 'sub_action' ];
			switch ( $action ) {

				case 'retrieve_table_data':
					$builder = new BuildIpRulesTableData();
					$builder->table_data = $this->action_data[ 'table_data' ] ?? [];
					$response = [
						'success'        => true,
						'datatable_data' => $builder->build(),
					];
					break;

				default:
					throw new \Exception( 'Not a supported IP Rules table sub_action: '.$action );
			}
		}
		catch ( \Exception $e ) {
			$response = [
				'success'     => false,
				'page_reload' => true,
				'message'     => $e->getMessage(),
			];
		}

		$resp->action_response_data = $response;
	}
}