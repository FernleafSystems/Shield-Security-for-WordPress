<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogTable\BuildAuditTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs\GetRequestMeta;
use FernleafSystems\Wordpress\Services\Services;

class ActivityLogTableAction extends BaseAction {

	const SLUG = 'logtable_action';
	const PRIMARY_MOD = 'audit_trail';

	protected function exec() {
		try {
			$action = Services::Request()->post( 'sub_action' );
			switch ( $action ) {

				case 'retrieve_table_data':
					$response = $this->retrieveTableData();
					break;

				case 'get_request_meta':
					$response = $this->getRequestMeta();
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
		$builder = ( new BuildAuditTableData() )->setMod( $this->primary_mod );
		$builder->table_data = (array)Services::Request()->post( 'table_data', [] );
		return [
			'success'        => true,
			'datatable_data' => $builder->build(),
		];
	}

	private function getRequestMeta() :array {
		return [
			'success' => true,
			'html'    => ( new GetRequestMeta() )
				->setMod( $this->getCon()->getModule_Data() )
				->retrieve( Services::Request()->post( 'rid' ) )
		];
	}
}