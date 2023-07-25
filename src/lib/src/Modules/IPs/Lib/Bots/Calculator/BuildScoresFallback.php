<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator;

use FernleafSystems\Wordpress\Services\Services;

class BuildScoresFallback extends BaseBuildScores {

	public function build() :array {
		$scores = [];
		foreach ( $this->getAllFields( true ) as $field ) {
			$scores[ $field ] = $this->{'score_'.$field}();
		}
		$scores[ 'known' ] = $this->score_known();
		if ( Services::Request()->ts() - $this->getRecord()->created_at < 20 ) {
			$scores[ 'baseline' ] = 35;
		}
		return $scores;
	}

	private function score_auth() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < \DAY_IN_SECONDS ? 175 : 150;
		}
		return $score;
	}

	private function score_bt404() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < \HOUR_IN_SECONDS ? -15 : -5;
		}
		return $score;
	}

	private function score_btcheese() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < \DAY_IN_SECONDS ? -65 : -45;
		}
		return $score;
	}

	private function score_btfake() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < \DAY_IN_SECONDS ? -75 : -45;
		}
		return $score;
	}

	private function score_btinvalidscript() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < \DAY_IN_SECONDS ? -25 : -15;
		}
		return $score;
	}

	private function score_btloginfail() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < \MINUTE_IN_SECONDS ? -35 : -15;
		}
		return $score;
	}

	private function score_btlogininvalid() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < \HOUR_IN_SECONDS ? -85 : -55;
		}
		return $score;
	}

	private function score_btua() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < \DAY_IN_SECONDS ? -35 : -25;
		}
		return $score;
	}

	private function score_btxml() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < \DAY_IN_SECONDS ? -55 : -35;
		}
		return $score;
	}

	private function score_cooldown() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < \MINUTE_IN_SECONDS ? -25 : -15;
		}
		return $score;
	}

	private function score_firewall() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < \DAY_IN_SECONDS ? -35 : -15;
		}
		return $score;
	}

	private function score_offense() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < \MINUTE_IN_SECONDS ? -35 : -25;
		}
		return $score;
	}

	private function score_blocked() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < \DAY_IN_SECONDS ? -55 : -45;
		}
		return $score;
	}

	private function score_unblocked() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < \DAY_IN_SECONDS ? 100 : 75;
		}
		return $score;
	}

	private function score_bypass() :int {
		return $this->lastAtTs( __FUNCTION__ ) > 0 ? 150 : 0;
	}

	private function score_captchapass() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < \DAY_IN_SECONDS ? 55 : 25;
		}
		return $score;
	}

	private function score_ratelimit() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < \MINUTE_IN_SECONDS ? -55 : -25;
		}
		return $score;
	}

	private function score_captchafail() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < \HOUR_IN_SECONDS ? -55 : -25;
		}
		return $score;
	}

	private function score_humanspam() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < \DAY_IN_SECONDS ? -30 : -15;
		}
		return $score;
	}

	private function score_markspam() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < \WEEK_IN_SECONDS ? -50 : -25;
		}
		return $score;
	}

	private function score_unmarkspam() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = 0;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < \WEEK_IN_SECONDS ? 75 : 35;
		}
		return $score;
	}

	private function score_frontpage() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = -15;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < \HOUR_IN_SECONDS ? 25 : 15;
		}
		return $score;
	}

	private function score_loginpage() :int {
		return $this->lastAtTs( __FUNCTION__ ) > 0 ? 15 : 0;
	}

	private function score_notbot() :int {
		if ( $this->lastAtTs( __FUNCTION__ ) === 0 ) {
			$score = -10;
		}
		else {
			$score = $this->diffTs( __FUNCTION__ ) < \HOUR_IN_SECONDS ? 150 : 75;
		}
		return $score;
	}
}