<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Enum,
	Utility
};

class UserSessionTokenDuration extends Base {

	use Traits\TypeSession;

	protected function execConditionCheck() :bool {
		return ( new Utility\PerformConditionMatch(
			self::con()->comps->session->current()->shield[ 'token_duration' ] ?? 0,
			$this->p->match_value,
			$this->p->match_type
		) )->doMatch();
	}

	public function getDescription() :string {
		return __( 'Is the current user session token age less than or greater than the provided value.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'match_type'  => [
				'type'      => Enum\EnumParameters::TYPE_ENUM,
				'type_enum' => [
					Enum\EnumMatchTypes::MATCH_TYPE_GREATER_THAN,
					Enum\EnumMatchTypes::MATCH_TYPE_LESS_THAN,
				],
				'default'   => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_value' => [
				'type'  => Enum\EnumParameters::TYPE_INT,
				'label' => __( 'Current Session Token Age (seconds)', 'wp-simple-firewall' ),
			],
		];
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => Enum\EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => IsLoggedInNormal::class,
				],
				[
					'conditions' => $this->getDefaultConditionCheckCallable(),
				],
			]
		];
	}
}