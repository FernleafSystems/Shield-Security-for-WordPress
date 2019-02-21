<?php

class ICWP_WPSF_Processor_Plugin_CronHourly extends ICWP_WPSF_Processor_BaseWpsf {

	use \FernleafSystems\Wordpress\Plugin\Shield\Crons\StandardCron;

	public function run() {
		parent::run();
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