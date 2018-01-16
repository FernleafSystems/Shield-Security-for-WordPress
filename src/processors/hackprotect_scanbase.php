<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect_ScanBase' ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wpsf.php' );

abstract class ICWP_WPSF_Processor_HackProtect_ScanBase extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		$this->setupCron();
	}

	protected function setupCron() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();
		try {
			$this->loadWpCronProcessor()
				 ->setRecurrence( $this->getCronRecurrence() )
				 ->createCronJob(
					 $this->getCronName(),
					 $this->getCronCallback()
				 );
		}
		catch ( Exception $oE ) {
		}
		add_action( $oFO->prefix( 'delete_plugin' ), array( $this, 'deleteCron' ) );
	}

	protected function getCronRecurrence() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();
		return $oFO->prefix( sprintf( 'per-day-%s', $this->getCronFrequency() ) );
	}

	/**
	 * @return callable
	 */
	abstract protected function getCronCallback();

	/**
	 * @return int
	 */
	abstract protected function getCronFrequency();

	/**
	 * @return string
	 */
	abstract protected function getCronName();

	/**
	 */
	public function deleteCron() {
		$this->loadWpCronProcessor()->deleteCronJob( $this->getCronName() );
	}
}