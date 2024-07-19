<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Rules\{
	Ops as RulesDB,
	RuleRecords
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\{
	ParseRuleBuilderForm,
	RuleFormBuilderVO
};

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
			$action = $this->action_data[ 'builder_action' ] ?? '';
			$editRuleID = (int)( $rawForm[ 'edit_rule_id' ] ?? -1 );

			if ( $action === 'reset' ) {

				$asDraft = true;
				$parsed = ( new ParseRuleBuilderForm( [] ) )->parseForm();

				if ( $editRuleID >= 0 ) {
					try {
						$recordToReset = ( new RuleRecords() )->byID( $editRuleID );
						if ( empty( $recordToReset->form ) ) {
							$parsed->edit_rule_id = $editRuleID;
							$msg = 'Rule Reset';
						}
						else {
							$parsed = ( new RuleFormBuilderVO() )->applyFromArray( $recordToReset->form );
							$msg = 'Rule reset to previous saved state';
						}
					}
					catch ( \Exception $e ) {
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
				// if we're going to update/save the rule
				if ( !$asDraft ) {
					// purge any unset condition
					if ( $parsed->has_unset_condition ) {
						$conditions = $parsed->conditions;
						\array_pop( $conditions );
						$parsed->conditions = $conditions;
					}
					// purge any unset responses
					if ( $parsed->has_unset_response ) {
						$responses = $parsed->responses;
						\array_pop( $responses );
						$parsed->responses = $responses;
					}
				}
			}

			$parsed->form_builder_version = $con->cfg->version();

			try {
				$record = $con->db_con->rules->insertFromForm( $parsed, $asDraft );
				$success = true;
				if ( !$asDraft ) {
					$msg = __( 'Rule Saved' );
					$con->rules->buildAndStore();
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