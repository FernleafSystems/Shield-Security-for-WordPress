<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Rules\{
	Ops as RulesDB,
	RuleRecords
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\{
	GetAvailable,
	ParseRuleBuilderForm,
	RuleFormBuilderVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\{
	EnumConditions,
	EnumLogic
};
use FernleafSystems\Wordpress\Services\Services;

class RuleBuilder extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_rules_rule_builder';
	public const TEMPLATE = '/components/rules/rule_builder.twig';

	protected function getRenderData() :array {
		$req = Services::Request()->ts();
		$con = self::con();

		$parsed = null;

		// Either we're editing a rule, or starting a new rule.
		$record = null;
		$maybeEditRuleID = $this->action_data[ 'edit_rule_id' ] ?? -1;
		if ( $maybeEditRuleID >= 0 ) {
			foreach ( ( new RuleRecords() )->getCustom() as $maybe ) {
				if ( $maybe->id === (int)$maybeEditRuleID ) {
					$record = $maybe;
					break;
				}
			}
		}

		// find a potential recent (5 minutes) draft.
		if ( empty( $record ) ) {
			/** @var ?RulesDB\Record $record */
			$record = ( new RuleRecords() )->getLatestFirstDraft();
		}

		// Parse the record into a usable form
		if ( !empty( $record ) && ( !empty( $record->form_draft ) || !empty( $record->form ) ) ) {
			$asDraft = $req - $record->updated_at < MINUTE_IN_SECONDS*5 && !empty( $record->form_draft );
			$parsed = ( new RuleFormBuilderVO() )->applyFromArray( $asDraft ? $record->form_draft : $record->form );
			$parsed->edit_rule_id = $record->id;
		}

		if ( empty( $parsed ) ) {
			$parsed = ( new ParseRuleBuilderForm( [] ) )->parseForm();
			$parsed->edit_rule_id = -1;
		}

		$isDraft = empty( $record ) || empty( $record->form );
		return [
			'flags'   => [
				'show_conditions_logic' => \count( $parsed->conditions ) > 1,
				'show_responses_logic'  => \count( $parsed->responses ) > 1,
				'show_responses'        => $parsed->count_set_conditions > 0,
				'allow_submit'          => $parsed->ready_to_create,
				'has_unset_condition'   => $parsed->has_unset_condition,
				'has_unset_response'    => $parsed->has_unset_response,
				'is_rule_edit'          => $parsed->edit_rule_id >= 0,
				'is_early_draft'        => $isDraft,
			],
			'imgs'    => [
				'icon_delete' => $con->svgs->raw( 'trash3-fill.svg' ),
			],
			'strings' => [
				'title'             => $isDraft ? __( 'Create New Rule', 'wp-simple-firewall' )
					: sprintf( __( 'Edit Rule #%s (%s)', 'wp-simple-firewall' ), $record->id, $record->name ),
				'create_rule'       => __( 'Create New Rule', 'wp-simple-firewall' ),
				'update_rule'       => __( 'Update This Rule', 'wp-simple-firewall' ),
				'creating_new_rule' => __( 'Create New Rule', 'wp-simple-firewall' ),
				'if'                => __( 'If', 'wp-simple-firewall' ),
				'then'              => __( 'Then', 'wp-simple-firewall' ),
				'reset'             => __( 'Reset', 'wp-simple-firewall' ),
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
					'value'   => EnumLogic::LOGIC_AND,
					'options' => [
						EnumLogic::LOGIC_AND => \strtoupper( __( 'and', 'wp-simple-firewall' ) ),
					],
				],
				'rule_name'            => $parsed->name,
				'rule_description'     => $parsed->description,
				'all_conditions'       => GetAvailable::Conditions(),
				'all_responses'        => GetAvailable::Responses(),
			]
		];
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