<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator;

class BuildScores extends BaseBuildScores {

	/**
	 * @var ScoreLogic
	 */
	private $logic;

	public function __construct() {
		$this->logic = new ScoreLogic();
	}

	public function build() :array {
		$scores = [];
		foreach ( $this->getAllFields() as $field ) {
			$scores[ $field ] = $this->calcFieldScore( $field );
		}

		$scores[ 'known' ] = $this->score_known();

		return $scores;
	}

	private function calcFieldScore( string $field ) :int {
		$logic = $this->logic->getFieldScoreLogic( $field );

		// -1 represents the default if none of the following boundaries are satisfied
		$score = $logic[ -1 ] ?? 0;

		if ( $this->lastAtTs( $field ) === 0 ) {
			$score = $logic[ 0 ] ?? 0;
		}
		else {
			unset( $logic[ 0 ] );
			\ksort( $logic );

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
}