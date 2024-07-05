<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator\CalculateVisitorBotScores;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Enum,
	Utility
};

class IsAdeScore extends Base {

	use Traits\TypeShield;

	public function getName() :string {
		return __( 'Is silentCAPTCHA score', 'wp-simple-firewall' );
	}

	public function getDescription() :string {
		return __( 'Is Visitor silentCAPTCHA Score ...', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		return ( new Utility\PerformConditionMatch(
			( new CalculateVisitorBotScores() )
				->setIP( $this->req->ip )
				->total(),
			$this->p->match_visitor_ade_score,
			$this->p->match_type
		) )->doMatch();
	}

	public function getParamsDef() :array {
		return [
			'match_type'              => [
				'type'      => Enum\EnumParameters::TYPE_ENUM,
				'type_enum' => Enum\EnumMatchTypes::MatchTypesForNumbers(),
				'default'   => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_visitor_ade_score' => [
				'type'    => Enum\EnumParameters::TYPE_INT,
				'label'   => __( 'Compare ADE Score To', 'wp-simple-firewall' ),
				'default' => 0,
			],
		];
	}
}