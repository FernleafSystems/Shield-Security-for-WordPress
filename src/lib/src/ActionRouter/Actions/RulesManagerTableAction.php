<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Rules\RuleRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\ReorderCustomRules;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\SecurityRules\BuildSecurityRulesTableData;

class RulesManagerTableAction extends BaseAction {

	public const SLUG = 'rules_manager_table_action';

	protected function exec() {
		$con = self::con();

		$response = [
			'success'     => false,
			'page_reload' => false,
			'message'     => '',
		];

		try {
			$RIDs = $this->action_data[ 'rids' ] ?? [];
			$action = $this->action_data[ 'sub_action' ] ?? '';
			switch ( $action ) {
				case 'retrieve_table_data':
					$response = $this->retrieveTableData();
					break;
				case 'deactivate_all':
					( new RuleRecords() )->disableAll();
					$response[ 'message' ] = __( 'All rules have been deactivated', 'wp-simple-firewall' );
					$response[ 'success' ] = true;
					break;
				case 'reorder':
					( new ReorderCustomRules() )->run( $RIDs );
					$response[ 'success' ] = true;
					break;
				default:

					$rule = \count( $RIDs ) === 1 ? ( new RuleRecords() )->byID( (int)\array_pop( $RIDs ) ) : null;
					if ( !empty( $rule ) ) {
						$updateData = [];
						switch ( $action ) {
							case 'delete':
								$con->db_con->rules->getQueryDeleter()->deleteRecord( $rule );
								$msg = __( 'Rule deleted', 'wp-simple-firewall' );
								break;
							case 'activate':
								$updateData[ 'is_active' ] = 1;
								$msg = __( 'Rule Activated', 'wp-simple-firewall' );
								break;
							case 'deactivate':
								$updateData[ 'is_active' ] = 0;
								$msg = __( 'Rule Deactivated', 'wp-simple-firewall' );
								break;
							case 'set_to_export':
								$updateData[ 'can_export' ] = 1;
								$msg = __( 'Rule will be exported during sync', 'wp-simple-firewall' );
								break;
							case 'set_no_export':
								$updateData[ 'can_export' ] = 0;
								$msg = __( "Rule won't be exported during sync", 'wp-simple-firewall' );
								break;
							default:
								throw new \Exception( 'Not a supported Sessions table sub_action: '.$this->action_data[ 'sub_action' ] );
						}

						$response[ 'message' ] = $msg;
						$response[ 'success' ] = true;

						if ( !empty( $updateData ) ) {
							$response[ 'success' ] = $con->db_con->rules->getQueryUpdater()
																		->updateRecord( $rule, $updateData );
						}
					}
					else {
						$response[ 'message' ] = __( 'Invalid Rule' );
					}
			}

			$con->rules->buildAndStore();
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
		$builder = new BuildSecurityRulesTableData();
		$builder->table_data = $this->action_data[ 'table_data' ];
		return [
			'success'        => true,
			'datatable_data' => $builder->build(),
		];
	}
}