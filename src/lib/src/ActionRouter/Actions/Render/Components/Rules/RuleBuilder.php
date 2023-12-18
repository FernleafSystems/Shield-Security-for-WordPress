<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\RequestBypassesAllRestrictions;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\GetAvailable;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\ParseRuleBuilderForm;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\RuleFormBuilderVO;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumConditions;
use FernleafSystems\Wordpress\Services\Services;

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
				$savedForm = self::con()->rules->getCustomRuleForms()[ $maybeEditRuleID ] ?? null;
				if ( !empty( $savedForm ) ) {
					$parsed = ( new RuleFormBuilderVO() )->applyFromArray( $savedForm );
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
				$opts = $con->getModule_Plugin()->opts();
				$parsed->form_builder_version = $con->cfg->version();
				$parsed->created_at = Services::Request()->ts();

				$custom = $opts->getOpt( 'custom_rules' );
				if ( $parsed->edit_rule_id >= 0 ) {
					$custom[ $parsed->edit_rule_id ] = $parsed->getRawData();
				}
				else {
					$custom[] = $parsed->getRawData();
				}

				$opts->setOpt( 'custom_rules', $custom );
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
				],
				'types'                => $this->conditionTypesForDisplay(),
				'conditions_logic'     => [
					'name'    => 'conditions_logic',
					'value'   => $parsed->conditions_logic,
					'options' => [
						Constants::LOGIC_AND => \strtoupper( __( 'and', 'wp-simple-firewall' ) ),
						Constants::LOGIC_OR  => \strtoupper( __( 'or', 'wp-simple-firewall' ) ),
					],
				],
				'responses_logic'      => [
					'name'    => 'responses_logic',
					'value'   => Constants::LOGIC_AND,
					'options' => [
						Constants::LOGIC_AND => \strtoupper( __( 'and', 'wp-simple-firewall' ) ),
					],
				],
				'rule_name'            => $parsed->name,
				'rule_description'     => $parsed->description,
				'all_conditions'       => GetAvailable::Conditions(),
				'all_responses'        => GetAvailable::Responses(),
				'warnings'             => $parsed->warnings,
			]
		];
	}

	private function defaultForm() :array {
		return [
			'condition_1'        => RequestBypassesAllRestrictions::Slug(),
			'condition_1_invert' => Constants::LOGIC_INVERT,
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