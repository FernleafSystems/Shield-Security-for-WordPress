<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Table\BuildIpRulesTableData;
use FernleafSystems\Wordpress\Services\Services;

class IpRulesTableAction extends IpsBase {

	const SLUG = 'iprulestable_action';

	protected function exec() {
		$resp = $this->response();
		try {
			$action = Services::Request()->post( 'sub_action' );
			switch ( $action ) {

				case 'retrieve_table_data':
					$builder = ( new BuildIpRulesTableData() )->setMod( $this->primary_mod );
					$builder->table_data = Services::Request()->post( 'table_data', [] );
					$response = [
						'success'        => true,
						'datatable_data' => $builder->build(),
					];
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

		$resp->action_response_data = $response;
	}
}