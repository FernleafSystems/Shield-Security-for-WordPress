<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\EntryVoConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals\EntryVO;
use FernleafSystems\Wordpress\Services\Services;

class BuildScores {

	use EntryVoConsumer;

	public function build() :array {
		$scores = [];
		foreach ( $this->getAllFields( true ) as $field ) {
			$scores[ $field ] = $this->{'score_'.$field}();
		}
		return $scores;
	}

	private function score_auth() :int {
		return $this->getRecord()->auth_at > 0 ? -100 : 0;
	}

	private function score_bt404() :int {
		return $this->getRecord()->bt404_at > 0 ? 35 : 0;
	}

	private function score_btcheese() :int {
		return $this->getRecord()->btcheese_at > 0 ? 100 : 0;
	}

	private function score_btfake() :int {
		return $this->getRecord()->btfake_at > 0 ? 100 : 0;
	}

	private function score_btinvalidscript() :int {
		return $this->getRecord()->btinvalidscript_at > 0 ? 75 : 0;
	}

	private function score_btloginfail() :int {
		return $this->getRecord()->btloginfail_at > 0 ? 50 : 0;
	}

	private function score_btlogininvalid() :int {
		return $this->getRecord()->btlogininvalid_at > 0 ? 85 : 0;
	}

	private function score_btua() :int {
		return $this->getRecord()->btua_at > 0 ? 25 : 0;
	}

	private function score_btxml() :int {
		return $this->getRecord()->btxml_at > 0 ? 75 : 0;
	}

	private function score_offense() :int {
		return $this->getRecord()->offense_at > 0 ? 75 : 0;
	}

	private function score_blocked() :int {
		return $this->getRecord()->blocked_at > 0 ? 95 : 0;
	}

	private function score_unblocked() :int {
		return $this->getRecord()->unblocked_at > 0 ? -100 : 0;
	}

	private function score_bypass() :int {
		return $this->getRecord()->bypass_at > 0 ? -150 : 0;
	}

	private function score_markspam() :int {
		return $this->getRecord()->markspam_at > 0 ? 50 : 0;
	}

	private function score_unmarkspam() :int {
		return $this->getRecord()->unmarkspam_at > 0 ? -75 : 0;
	}

	private function score_notbot() :int {
		$entry = $this->getRecord();

		if ( $entry->notbot_at === 0 ) {
			$score = 85;
		}
		elseif ( Services::Request()->ts() - $entry->notbot_at > HOUR_IN_SECONDS ) {
			$score = 50;
		}
		else {
			$score = -50; // set recently
		}

		return $score;
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