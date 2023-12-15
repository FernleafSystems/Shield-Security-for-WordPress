<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\GetAvailable;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\ParseRuleBuilderForm;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumConditions;

class RuleBuilder extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_rules_rule_builder';
	public const TEMPLATE = '/components/rules/rule_builder.twig';

	protected function getRenderData() :array {
		$con = self::con();

		$processor = new ParseRuleBuilderForm(
			$this->action_data[ 'rule_form' ] ?? [],
			$this->action_data[ 'builder_action' ] ?? '',
			$this->action_data[ 'builder_action_vars' ] ?? []
		);

		$parsed = $processor->parseForm();
		if ( $parsed->ready_to_create && $this->action_data[ 'builder_action' ] === 'create_rule' ) {
			$opts = self::con()->getModule_Plugin()->opts();
			$custom = $opts->getOpt( 'custom_rules' );
			$custom[] = $parsed->getRawData();
			$opts->setOpt( 'custom_rules', $custom );
		}

		return [
			'flags' => [
				'show_conditions_logic' => \count( $parsed->conditions ) > 1,
				'show_responses_logic'  => \count( $parsed->responses ) > 1,
				'show_responses'        => $parsed->count_set_conditions > 0,
				'allow_submit'          => $parsed->ready_to_create,
				'has_unset_condition'   => $parsed->has_unset_condition,
				'has_unset_response'    => $parsed->has_unset_response,
			],
			'imgs'  => [
				'icon_delete' => $con->svgs->raw( 'trash3-fill.svg' ),
			],
			'vars'  => [
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
				'rule_name'            => $parsed->rule_name,
				'rule_description'     => $parsed->rule_description,
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