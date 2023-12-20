<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\GetAvailable;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\ParseRuleBuilderForm;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\RuleFormBuilderVO;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumConditions;

class RuleBuilder extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_rules_rule_builder';
	public const TEMPLATE = '/components/rules/rule_builder.twig';

	protected function getRenderData() :array {
		$con = self::con();

		$parsed = null;

		// Either we're editing a rule, or starting a new rule.
		if ( !isset( $this->action_data[ 'rule_form' ] ) ) {
			$maybeEditRuleID = $this->action_data[ 'edit_rule_id' ] ?? -1;
			if ( $maybeEditRuleID >= 0 ) {
				foreach ( self::con()->rules->getCustomRuleForms() as $record ) {
					if ( $record->id === (int)$maybeEditRuleID ) {
						$parsed = ( new RuleFormBuilderVO() )->applyFromArray( $record->form );
					}
				}
				if ( !empty( $parsed ) ) {
					$parsed->edit_rule_id = $maybeEditRuleID;
				}
			}
			else {
				$parsed = ( new ParseRuleBuilderForm( $this->defaultForm(), 'add_condition', [] ) )->parseForm();
				$parsed->edit_rule_id = -1;
			}
		}

		if ( empty( $parsed ) ) {
			$parsed = ( new ParseRuleBuilderForm(
				$this->action_data[ 'rule_form' ] ?? [],
				$this->action_data[ 'builder_action' ] ?? '',
				$this->action_data[ 'builder_action_vars' ] ?? []
			) )->parseForm();

			if ( $parsed->ready_to_create && $this->action_data[ 'builder_action' ] === 'create_rule' ) {
				$parsed->form_builder_version = $con->cfg->version();
				try {
					$con->db_con->getDbH_Rules()->insertFromForm( $parsed );
				}
				catch ( \Exception $e ) {
					error_log( $e->getMessage() );
				}
			}
		}

		return [
			'flags'   => [
				'show_conditions_logic' => \count( $parsed->conditions ) > 1,
				'show_responses_logic'  => \count( $parsed->responses ) > 1,
				'show_responses'        => $parsed->count_set_conditions > 0,
				'allow_submit'          => $parsed->ready_to_create,
				'has_unset_condition'   => $parsed->has_unset_condition,
				'has_unset_response'    => $parsed->has_unset_response,
				'is_rule_edit'          => $parsed->edit_rule_id >= 0,
			],
			'imgs'    => [
				'icon_delete' => $con->svgs->raw( 'trash3-fill.svg' ),
			],
			'strings' => [
				'create_rule' => __( 'Create This Rule', 'wp-simple-firewall' ),
				'update_rule' => __( 'Update This Rule', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'edit_rule_id'         => $parsed->edit_rule_id,
				'count_set_conditions' => $parsed->count_set_conditions,
				'count_set_responses'  => $parsed->count_set_responses,
				'form_data'            => [
					'conditions' => $parsed->conditions,
					'responses'  => $parsed->responses,
					'checks'     => $parsed->checks,
				],
				'types'                => $this->conditionTypesForDisplay(),
				'conditions_logic'     => [
					'name'    => 'conditions_logic',
					'value'   => $parsed->conditions_logic,
					'options' => [
						EnumLogic::LOGIC_AND => \strtoupper( __( 'and', 'wp-simple-firewall' ) ),
						EnumLogic::LOGIC_OR  => \strtoupper( __( 'or', 'wp-simple-firewall' ) ),
					],
				],
				'responses_logic'      => [
					'name'    => 'responses_logic',
					'value'   => \FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic::LOGIC_AND,
					'options' => [
						\FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic::LOGIC_AND => \strtoupper( __( 'and', 'wp-simple-firewall' ) ),
					],
				],
				'rule_name'            => $parsed->name,
				'rule_description'     => $parsed->description,
				'all_conditions'       => GetAvailable::Conditions(),
				'all_responses'        => GetAvailable::Responses(),
			]
		];
	}

	private function defaultForm() :array {
		return [];
	}

	private function conditionTypesForDisplay() :array {
		$theTypes = [];
		$rawTypes = EnumConditions::Types();
		foreach ( $rawTypes as $type ) {
			$theTypes[ $type ] = \ucfirst( __( $type, 'wp-simple-firewall' ) );
		}
		return $theTypes;
	}
}