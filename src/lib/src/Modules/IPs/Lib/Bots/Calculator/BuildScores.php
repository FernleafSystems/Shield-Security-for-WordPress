<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\EntryVoConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals\EntryVO;
use FernleafSystems\Wordpress\Services\Services;

class BuildScores {

	use EntryVoConsumer;

	public function build() :array {
		$scores = [];
		if ( !empty( $this->getRecord()->created_at ) ) { // all new IPs start from 0.
			foreach ( $this->getAllFields( true ) as $field ) {
				$scores[ $field ] = $this->{'score_'.$field}();
			}
		}
		return $scores;
	}

	private function score_auth() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < DAY_IN_SECONDS ? -150 : -100;
		}
		return $score;
	}

	private function score_bt404() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < HOUR_IN_SECONDS ? 45 : 25;
		}
		return $score;
	}

	private function score_btcheese() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < DAY_IN_SECONDS ? 100 : 75;
		}
		return $score;
	}

	private function score_btfake() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < DAY_IN_SECONDS ? 100 : 75;
		}
		return $score;
	}

	private function score_btinvalidscript() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < DAY_IN_SECONDS ? 75 : 35;
		}
		return $score;
	}

	private function score_btloginfail() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < HOUR_IN_SECONDS ? 50 : 25;
		}
		return $score;
	}

	private function score_btlogininvalid() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < DAY_IN_SECONDS ? 85 : 55;
		}
		return $score;
	}

	private function score_btua() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < DAY_IN_SECONDS ? 35 : 25;
		}
		return $score;
	}

	private function score_btxml() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < DAY_IN_SECONDS ? 75 : 35;
		}
		return $score;
	}

	private function score_cooldown() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < MINUTE_IN_SECONDS ? 35 : 15;
		}
		return $score;
	}

	private function score_offense() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < DAY_IN_SECONDS ? 75 : 35;
		}
		return $score;
	}

	private function score_blocked() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < DAY_IN_SECONDS ? 95 : 55;
		}
		return $score;
	}

	private function score_unblocked() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < DAY_IN_SECONDS ? -100 : -75;
		}
		return $score;
	}

	private function score_bypass() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < DAY_IN_SECONDS ? -150 : -100;
		}
		return $score;
	}

	private function score_markspam() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < DAY_IN_SECONDS ? 50 : 25;
		}
		return $score;
	}

	private function score_unmarkspam() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < DAY_IN_SECONDS ? -75 : -35;
		}
		return $score;
	}

	private function score_notbot() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 85;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < HOUR_IN_SECONDS ? -75 : -25;
		}
		return $score;
	}

	private function lastAtTs( $fieldFunction ) :int {
		$field = str_replace( 'score_', '', $fieldFunction ).'_at';
		return $this->getRecord()->{$field} ?? 0;
	}

	private function diffTs( $fieldFunction ) :int {
		$field = str_replace( 'score_', '', $fieldFunction ).'_at';
		return Services::Request()->ts() - ( $this->getRecord()->{$field} ?? 0 );
	}

	private function getAllFields( $filterForMethods = false ) :array {
		$fields = array_map(
			function ( $col ) {
				return str_replace( '_at', '', $col );
			},
			array_filter(
				array_keys( $this->getRecord()->getRawData() ),
				function ( $col ) {
					return preg_match( '#_at$#', $col ) &&
						   !in_array( $col, [ 'updated_at', 'created_at', 'deleted_at' ] );
				}
			)
		);

		if ( $filterForMethods ) {
			$fields = array_filter( $fields, function ( $field ) {
				return method_exists( $this, 'score_'.$field );
			} );
		}

		return $fields;
	}

	private function getRecord() :EntryVO {
		return $this->getEntryVO();
	}
}