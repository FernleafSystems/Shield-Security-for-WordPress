<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator\CalculateVisitorBotScores;

class IsAdeScoreAtLeast extends Base {

	use Traits\TypeShield;
	use Traits\RequestIP;

	public function getDescription() :string {
		return __( 'Is Visitor ADE Score At Least...', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		return ( new CalculateVisitorBotScores() )
				   ->setIP( $this->getRequestIP() )
				   ->total() > $this->params[ 'score' ];
	}

	public function getParamsDef() :array {
		return [
			'score' => [
				'type'  => 'int',
				'label' => __( 'Minimum ADE Score', 'wp-simple-firewall' ),
			],
		];
	}
}