<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\Rules\Ops as RulesDB;

class RulesManagerActions extends BaseAction {

	public const SLUG = 'rules_manager_actions';

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
					$updateData = [];
					switch ( $managerAction[ 'action' ] ?? '' ) {
						case 'delete':
							$success = $dbh->getQueryDeleter()->deleteById( $ruleID );
							$msg = __( 'Rule Deleted', 'wp-simple-firewall' );
							break;
						case 'activate':
							$updateData = [
								'is_active' => 1,
							];
							$msg = __( 'Rule Activated', 'wp-simple-firewall' );
							break;
						case 'deactivate':
							$updateData = [
								'is_active' => 0,
							];
							$msg = __( 'Rule Deactivated', 'wp-simple-firewall' );
							break;
						case 'set_to_export':
							$updateData = [
								'can_export' => 1,
							];
							$msg = __( 'Rule will be exported during sync', 'wp-simple-firewall' );
							break;
						case 'set_no_export':
							$updateData = [
								'can_export' => 0,
							];
							$msg = __( "Rule won't be exported during sync", 'wp-simple-firewall' );
							break;
						default:
							break;
					}

					if ( !empty( $updateData ) ) {
						$success = $dbh->getQueryUpdater()->updateRecord( $rule, $updateData );
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