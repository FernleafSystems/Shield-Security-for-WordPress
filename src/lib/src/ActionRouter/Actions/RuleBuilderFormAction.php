<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\Rules\Ops as RulesDB;

class RuleBuilderFormAction extends BaseAction {

	public const SLUG = 'rule_builder_form_action';

	protected function exec() {
		$con = self::con();

		$managerAction = $this->action_data[ 'manager_action' ] ?? [];

		$success = false;
		$msg = '';
		if ( !empty( $managerAction ) ) {
			$ruleID = $managerAction[ 'rule_id' ] ?? -1;
			if ( \is_numeric( $ruleID ) && $ruleID > 0 ) {
				$dbh = $con->db_con->getDbH_Rules();
				/** @var ?RulesDB\Record $rule */
				$rule = $dbh->getQuerySelector()->byId( (int)$ruleID );
				if ( empty( $rule ) ) {
					$msg = __( 'No Such Rule', 'wp-simple-firewall' );
				}
				else {
					switch ( $managerAction[ 'action' ] ?? '' ) {
						case 'delete':
							$success = $dbh->getQueryDeleter()->deleteById( $ruleID );
							$msg = __( 'Rule Deleted', 'wp-simple-firewall' );
							break;
						case 'activate':
							$success = $dbh->getQueryUpdater()->updateRecord( $rule, [
								'is_active' => 1,
							] );
							$msg = __( 'Rule Activated', 'wp-simple-firewall' );
							break;
						case 'deactivate':
							$success = $dbh->getQueryUpdater()->updateRecord( $rule, [
								'is_active' => 0,
							] );
							$msg = __( 'Rule Deactivated', 'wp-simple-firewall' );
							break;
						default:
							break;
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