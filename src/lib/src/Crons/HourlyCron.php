<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Crons;

use FernleafSystems\Wordpress\Plugin\Shield;

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
	 * Use the included action to hook into the plugin's daily cron
	 */
	public function runCron() {
		do_action( $this->getCon()->prefix( 'hourly_cron' ) );
	}
}