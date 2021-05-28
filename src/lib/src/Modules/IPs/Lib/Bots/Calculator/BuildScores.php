<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Reputation\BotScoringLogic;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

class BuildScores extends BaseBuildScores {

	private $logic = [];

	public function build() :array {

		$logic = $this->getScoringLogic();
		if ( empty( $logic ) ) { // fallback to original built-in scoring.
			$scores = ( new BuildScoresFallback() )
				->setEntryVO( $this->getEntryVO() )
				->setMod( $this->getMod() )
				->build();
		}
		else {
			$scores = [];
			foreach ( $this->getAllFields() as $field ) {
				$scores[ $field ] = $this->getFieldScore( $field );
			}
		}

		$scores[ 'known' ] = $this->score_known();

		return $scores;
	}

	private function getFieldScore( string $field ) :int {
		$logic = $this->getFieldScoreLogic( $field );

		$score = $logic[ -1 ];

		if ( $this->lastAtTs( $field ) === 0 ) {
			$score = $logic[ 0 ];
		}
		else {
			unset( $logic[ 0 ] );
			ksort( $logic );

			$diff = $this->diffTs( $field );
			foreach ( $logic as $boundary => $boundaryScore ) {
				if ( $diff < $boundary ) {
					$score = $boundaryScore;
					break;
				}
			}
		}

		return (int)$score;
	}

	private function getFieldScoreLogic( $field ) :array {
		return $this->logic[ $field ] ?? [];
	}

	private function getScoringLogic() :array {
		if ( empty( $this->logic ) ) {
			$logic = Transient::Get( 'shield-bot-scoring-logic' );
			if ( empty( $logic ) ) {
				$logicLoader = ( new BotScoringLogic() )->setMod( $this->getCon()->getModule_Plugin() );
				$logicLoader->shield_net_params_required = false;
				$logic = $logicLoader->retrieve();
				if ( !empty( $logic ) ) {
					Transient::Set( 'shield-bot-scoring-logic', $logic );
				}
			}

			if ( is_array( $logic ) ) {
				$this->logic = $logic;
			}
		}

		return $this->logic;
	}
}