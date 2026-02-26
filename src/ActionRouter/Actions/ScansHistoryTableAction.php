<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\SecurityRules\BuildSecurityRulesTableData;

class ScansHistoryTableAction extends BaseAction {

	public const SLUG = 'scans_history_table_action';

	protected function exec() {
		$response = [
			'success'     => false,
			'page_reload' => false,
			'message'     => '',
		];

		try {
			if ( ( $this->action_data[ 'sub_action' ] ?? '' ) === 'retrieve_table_data' ) {
				$response = $this->retrieveTableData();
			}
			self::con()->rules->buildAndStore();
		}
		catch ( \Exception $e ) {
			$response = [
				'success'     => false,
				'page_reload' => false,
				'message'     => $e->getMessage(),
			];
		}

		$payloadSuccess = (bool)( $response[ 'success' ] ?? false );
		unset( $response[ 'success' ] );

		$this->response()
			 ->setPayload( $response )
			 ->setPayloadSuccess( $payloadSuccess );
	}

	private function retrieveTableData() :array {
		$builder = new BuildSecurityRulesTableData();
		$builder->table_data = $this->action_data[ 'table_data' ];
		return [
			'success'        => true,
			'datatable_data' => $builder->build(),
		];
	}
}
