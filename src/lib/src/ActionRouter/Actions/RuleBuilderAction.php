<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\Rules\Ops as RulesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\ParseRuleBuilderForm;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\RuleFormBuilderVO;

class RuleBuilderAction extends BaseAction {

	public const SLUG = 'rule_builder_action';

	protected function exec() {
		$con = self::con();

		$success = false;
		$record = null;

		$rawForm = $this->action_data[ 'rule_form' ] ?? [];
		if ( empty( $rawForm ) ) {
			$msg = __( 'Please provide the builder form' );
		}
		else {
			$dbh = $con->db_con->getDbH_Rules();

			$action = $this->action_data[ 'builder_action' ] ?? '';
			$editRuleID = (int)( $rawForm[ 'edit_rule_id' ] ?? -1 );

			if ( $action === 'reset' ) {

				$asDraft = true;
				$parsed = ( new ParseRuleBuilderForm( [] ) )->parseForm();

				if ( $editRuleID >= 0 ) {
					/** @var RulesDB\Record $recordToReset */
					$recordToReset = $dbh->getQuerySelector()->byId( $editRuleID );
					if ( empty( $recordToReset->form ) ) {
						$parsed->edit_rule_id = $editRuleID;
						$msg = 'Rule Reset';
					}
					else {
						$parsed = ( new RuleFormBuilderVO() )->applyFromArray( $recordToReset->form );
						$msg = 'Rule reset to previous saved state';
					}
				}
			}
			else {
				$parsed = ( new ParseRuleBuilderForm(
					$rawForm,
					$action,
					$this->action_data[ 'builder_action_vars' ] ?? []
				) )->parseForm();

				$asDraft = !$parsed->ready_to_create || $this->action_data[ 'builder_action' ] !== 'create_rule';
			}

			$parsed->form_builder_version = $con->cfg->version();

			try {
				$record = $dbh->insertFromForm( $parsed, $asDraft );
				$success = true;
				if ( !$asDraft ) {
					$msg = __( 'Rule Saved' );
				}
			}
			catch ( \Exception $e ) {
				$msg = $e->getMessage();
			}
		}

		$this->response()->action_response_data = [
			'success'      => $success,
			'message'      => $msg ?? '',
			'edit_rule_id' => $record instanceof RulesDB\Record ? $record->id : -1,
		];
	}
}