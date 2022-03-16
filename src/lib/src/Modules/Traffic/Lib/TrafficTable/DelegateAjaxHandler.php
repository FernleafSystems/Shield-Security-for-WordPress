<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\TrafficTable;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\Lib\UpgradeReqLogsTable;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables;
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

			default:
				throw new \Exception( 'Not a supported Audit Trail table sub_action: '.$action );
		}
		return $response;
	}

	/**
	 * @throws \Exception
	 */
	private function retrieveTableData() :array {
		( new UpgradeReqLogsTable() )
			->setMod( $this->getCon()->getModule_Data() )
			->execute();

		$builder = ( new BuildTrafficTableData() )->setMod( $this->getMod() );
		$builder->table_data = Services::Request()->post( 'table_data', [] );
		return [
			'success'        => true,
			'datatable_data' => $builder->build(),
		];
	}
}