<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator\CalculateVisitorBotScores;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\PerformConditionMatch;

/**
 * Represents a class for checking if the visitor has a certain ADE score.
 *
 * @property string $match_type The match type for the ADE score comparison.
 * @property string $score
 */
class IsAdeScore extends Base {

	use Traits\TypeShield;
	use Traits\RequestIP;

	public function getDescription() :string {
		return __( 'Is Visitor ADE Score ...', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		return ( new PerformConditionMatch(
			( new CalculateVisitorBotScores() )
				->setIP( $this->getRequestIP() )
				->total(),
			$this->score,
			$this->match_type
		) )->doMatch();
	}

	public function getParamsDef() :array {
		return [
			'match_type' => [
				'type'      => EnumParameters::TYPE_ENUM,
				'type_enum' => EnumMatchTypes::MatchTypesForNumbers(),
				'default'   => EnumMatchTypes::MATCH_TYPE_EQUALS_I,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'score'      => [
				'type'  => EnumParameters::TYPE_INT,
				'label' => __( 'Compare ADE Score To', 'wp-simple-firewall' ),
				'default' => 0,
			],
		];
	}
}