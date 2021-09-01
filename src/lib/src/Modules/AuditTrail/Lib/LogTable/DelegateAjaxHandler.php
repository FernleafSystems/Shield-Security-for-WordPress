<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogTable;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class DelegateAjaxHandler {

	use Shield\Modules\ModConsumer;

	/**
	 * @return array
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
	 * @return array
	 * @throws \Exception
	 */
	private function retrieveTableData() :array {
		return [
			'success' => true,
			'vars'    => [
				'data' => ( new LoadRawTableData() )
					->setMod( $this->getMod() )
					->loadForLogs()
			],
		];
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	private function getRequestMeta() :array {
		return [
			'success' => true,
			'html'    => ( new Shield\Modules\Traffic\DB\GetRequestMeta() )
				->setMod( $this->getCon()->getModule_Traffic() )
				->retrieve( Services::Request()->post( 'rid' ) )
		];
	}
}