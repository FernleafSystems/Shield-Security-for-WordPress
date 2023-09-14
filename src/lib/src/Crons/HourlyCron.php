<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Crons;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class HourlyCron extends BaseCron {

	/**
	 * @return string
	 */
	protected function getCronFrequency() {
		return 'hourly';
	}

	protected function getCronName() :string {
		return self::con()->prefix( 'hourly' );
	}

	public function getFirstRunTimestamp() :int {
		return Services::Request()
					   ->carbon( true )
					   ->addHour()
					   ->minute( \rand( 1, 59 ) )
					   ->second( 0 )->timestamp;
	}

	public function runCron() {
		do_action( self::con()->prefix( 'hourly_cron' ) );
	}
}