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

	/**
	 * @return string
	 * @throws \Exception
	 */
	protected function getCronName() {
		return $this->getCon()->prefix( 'hourly' );
	}

	/**
	 * @return int
	 */
	public function getFirstRunTimestamp() {
		$oCarb = Services::Request()
						 ->carbon( true )
						 ->addHours( 1 )
						 ->minute( 1 )
						 ->second( 0 );
		return $oCarb->timestamp;
	}

	public function runCron() {
		do_action( $this->getCon()->prefix( 'hourly_cron' ) );
	}
}