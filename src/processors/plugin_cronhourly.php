<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;

class ICWP_WPSF_Processor_Plugin_CronHourly extends Modules\BaseShield\ShieldProcessor {

	use \FernleafSystems\Wordpress\Plugin\Shield\Crons\StandardCron;

	public function run() {
		$this->setupCron();
	}

	/**
	 * @return string
	 */
	protected function getCronFrequency() {
		return 'hourly';
	}

	/**
	 * @return string
	 */
	protected function getCronName() {
		return $this->getMod()->prefix( 'hourly' );
	}

	/**
	 * Use the included action to hook into the plugin's daily cron
	 */
	public function runCron() {
		do_action( $this->getMod()->prefix( 'hourly_cron' ) );
	}
}