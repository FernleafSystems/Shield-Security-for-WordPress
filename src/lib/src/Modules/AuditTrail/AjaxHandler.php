<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogTable\BuildAuditTableData;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function getAjaxActionCallbackMap( bool $isAuth ) :array {
		$map = parent::getAjaxActionCallbackMap( $isAuth );
		if ( $isAuth ) {
			$map = array_merge( $map, [
				'logtable_action' => [ $this, 'ajaxExec_AuditTrailTableAction' ]
			] );
		}
		return $map;
	}

	public function ajaxExec_AuditTrailTableAction() :array {
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
					throw new \Exception( 'Not a supported Audit Trail table sub_action: '.$action );
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
		$builder = ( new BuildAuditTableData() )->setMod( $this->getMod() );
		$builder->table_data = (array)Services::Request()->post( 'table_data', [] );
		return [
			'success'        => true,
			'datatable_data' => $builder->build(),
		];
	}

	private function getRequestMeta() :array {
		return [
			'success' => true,
			'html'    => ( new Shield\Modules\Data\DB\ReqLogs\GetRequestMeta() )
				->setMod( $this->getCon()->getModule_Data() )
				->retrieve( Services::Request()->post( 'rid' ) )
		];
	}
}