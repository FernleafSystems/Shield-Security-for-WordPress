<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Crons;

use FernleafSystems\Wordpress\Services\Services;

class DailyCron extends BaseCron {

	/**
	 * @return string
	 */
	protected function getCronFrequency() {
		return 'daily';
	}

	protected function getCronName() :string {
		return self::con()->prefix( 'daily' );
	}

	public function getFirstRunTimestamp() :int {
		$hour = ( \rand( -3, 7 ) + 24 )%24;
		if ( $hour === 0 ) {
			$hour += \rand( 1, 7 );
		}

		$chosenHour = (int)apply_filters( 'shield/daily_cron_hour', $hour );
		if ( $chosenHour < 0 || $chosenHour > 23 ) {
			$chosenHour = $hour;
		}

		$carbon = Services::Request()
						  ->carbon( true )
						  ->minute( \rand( 1, 59 ) )
						  ->second( \rand( 1, 59 ) );
		if ( $carbon->hour >= $chosenHour ) {
			$carbon->addDay();
		}
		return $carbon->hour( $chosenHour )->timestamp;
	}

	/**
	 * Use the included action to hook into the plugin's daily cron
	 */
	public function runCron() {
		do_action( self::con()->prefix( 'daily_cron' ) );
	}
}