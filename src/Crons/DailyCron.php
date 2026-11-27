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
		// 21:00 to 07:00. wp_rand() returns absolute values, so the range can't start below zero.
		$hour = wp_rand( 21, 31 )%24;
		if ( $hour === 0 ) {
			$hour += wp_rand( 1, 7 );
		}

		$chosenHour = (int)apply_filters( 'shield/daily_cron_hour', $hour );
		if ( $chosenHour < 0 || $chosenHour > 23 ) {
			$chosenHour = $hour;
		}

		$carbon = Services::Request()
						  ->carbon( true )
						  ->minute( wp_rand( 1, 59 ) )
						  ->second( wp_rand( 1, 59 ) );
		if ( $carbon->hour >= $chosenHour ) {
			$carbon->addDay();
		}
		return $carbon->hour( $chosenHour )->timestamp;
	}

	/**
	 * Use the included action to hook into the plugin's daily cron
	 */
	public function runCron() {
		do_action( self::con()->prefix( 'daily_cron' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Prefixed at runtime: Controller::prefix() gives icwp-wpsf-<name>.
	}
}