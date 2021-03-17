<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Crons;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class DailyCron extends BaseCron {

	/**
	 * @return string
	 */
	protected function getCronFrequency() {
		return 'daily';
	}

	protected function getCronName() :string {
		return $this->getCon()->prefix( 'daily' );
	}

	public function getFirstRunTimestamp() :int {
		$hour = (int)apply_filters( 'shield/daily_cron_hour', 7 );
		if ( $hour < 0 || $hour > 23 ) {
			$hour = 7;
		}
		$carbon = Services::Request()
						 ->carbon( true )
						 ->minute( rand( 1, 59 ) )
						 ->second( 0 );
		if ( $carbon->hour >= $hour ) {
			$carbon->addDays( 1 );
		}
		return $carbon->hour( $hour )->timestamp;
	}

	/**
	 * Use the included action to hook into the plugin's daily cron
	 */
	public function runCron() {
		do_action( $this->getCon()->prefix( 'daily_cron' ) );
	}
}