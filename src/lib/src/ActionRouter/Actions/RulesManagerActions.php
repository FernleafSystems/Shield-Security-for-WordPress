<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\Rules\RuleRecords;

class RulesManagerActions extends BaseAction {

	public const SLUG = 'rules_manager_actions';

	protected function exec() {
		$con = self::con();

		$managerAction = $this->action_data[ 'manager_action' ] ?? [];

		$success = false;
		$msg = '';
		if ( !empty( $managerAction ) ) {

			$theAction = $managerAction[ 'action' ] ?? '';

			if ( $theAction === 'disable_all' ) {
				( new RuleRecords() )->disableAll();
				$msg = __( 'All rules have been disabled', 'wp-simple-firewall' );
				$success = true;
			}
			else {
				$ruleID = $managerAction[ 'rule_id' ] ?? -1;
				if ( \is_numeric( $ruleID ) && $ruleID > 0 ) {
					try {
						$dbh = $con->db_con->dbhRules();
						$rule = ( new RuleRecords() )->byID( (int)$ruleID );

						$updateData = [];
						switch ( $theAction ) {
							case 'delete':
								$success = $dbh->getQueryDeleter()->deleteById( $ruleID );
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
								break;
						}

						if ( !empty( $updateData ) ) {
							$success = $dbh->getQueryUpdater()->updateRecord( $rule, $updateData );
						}

						$con->rules->buildAndStore();
					}
					catch ( \Exception $e ) {
						$msg = __( 'No Such Rule', 'wp-simple-firewall' );
					}
				}
			}
		}

		$this->response()->action_response_data = [
			'success' => $success,
			'message' => $msg,
		];
	}
}