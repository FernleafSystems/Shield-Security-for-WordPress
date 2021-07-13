<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
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

			default:
				throw new \Exception( 'Not a supported scan tables sub_action: '.$action );
		}
		return $response;
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	private function retrieveTableData() :array {
		$req = Services::Request();
		return [
			'success' => true,
			'vars'    => [
				'data' => ( new LoadRawTableData() )
					->setMod( $this->getMod() )
					->loadFor( $req->post( 'type' ), $req->post( 'file' ) )
			],
		];
	}
}