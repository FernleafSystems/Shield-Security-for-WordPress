<?php

if ( class_exists( 'ICWP_WPSF_Processor_Traffic', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_Processor_Traffic extends ICWP_WPSF_Processor_BaseWpsf {

	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_Traffic $oFO */
		$oFO = $this->getFeature();
		$this->getProcessorLogger()->run();
	}

	/**
	 * @return ICWP_WPSF_Processor_TrafficLogger
	 */
	protected function getProcessorLogger() {
		$oPro = $this->getSubProcessor( 'traffic_logger' );
		if ( is_null( $oPro ) ) {
			/** @var ICWP_WPSF_FeatureHandler_Traffic $oFO */
			$oFO = $this->getFeature();
			require_once( dirname( __FILE__ ).'/traffic_logger.php' );
			$oPro = new ICWP_WPSF_Processor_TrafficLogger( $oFO );
			$this->aSubProcessors[ 'traffic_logger' ] = $oPro;
		}
		return $oPro;
	}
}