<?php

use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_Plugin_CronDaily extends ICWP_WPSF_Processor_BaseWpsf {

	use \FernleafSystems\Wordpress\Plugin\Shield\Crons\StandardCron;

	/**
	 */
	public function run() {
		parent::run();
		$this->setupCron();
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	protected function getCronName() {
		return $this->getMod()->prefix( 'daily' );
	}

	/**
	 * Use the included action to hook into the plugin's daily cron
	 */
	public function runCron() {
		do_action( $this->getMod()->prefix( 'daily_cron' ) );
	}
}