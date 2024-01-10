<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Enum,
	Utility
};

class UserSessionAge extends Base {

	use Traits\TypeSession;

	protected function execConditionCheck() :bool {
		$startedAt = self::con()
						 ->getModule_Plugin()
						 ->getSessionCon()
						 ->current()
						 ->shield[ 'session_started_at' ] ?? 0;
		return $startedAt > 0
			   &&
			   ( new Utility\PerformConditionMatch(
				   $this->req->carbon->timestamp - $startedAt,
				   $this->p->match_value,
				   $this->p->match_type
			   ) )->doMatch();
	}

	public function getDescription() :string {
		return __( 'Is the current user session age less than or greater than the provided value.', 'wp-simple-firewall' );
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
				'label' => __( 'Current Session Age (seconds)', 'wp-simple-firewall' ),
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