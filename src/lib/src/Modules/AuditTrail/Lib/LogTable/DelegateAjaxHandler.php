<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogTable;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class DelegateAjaxHandler {

	use Shield\Modules\ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function processAjaxAction() :array {
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
		return $response;
	}

	/**
	 * @throws \Exception
	 */
	private function retrieveTableData() :array {
		$tableData = Services::Request()->post( 'table_data', [] );

		$dataLoader = ( new LoadRawTableData() )->setMod( $this->getMod() );
		$dataLoader->start = (int)$tableData[ 'start' ];
		$dataLoader->length = (int)$tableData[ 'length' ];
		$dataLoader->search = (string)$tableData[ 'search' ][ 'value' ] ?? '';
		return [
			'success'        => true,
			'datatable_data' => [
				'data' => $dataLoader->loadForLogs()
			],
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