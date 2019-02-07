<?php

/**
 * Class ICWP_WPSF_Processor_Traffic
 */
class ICWP_WPSF_Processor_Traffic extends ICWP_WPSF_Processor_BaseWpsf {

	public function run() {
		$this->getProcessorLogger()->run();
	}

	/**
	 * @return ICWP_WPSF_Processor_TrafficLogger|mixed
	 */
	public function getProcessorLogger() {
		return $this->getSubPro( 'traffic_logger' );
	}

	/**
	 * @return array
	 */
	protected function getSubProMap() {
		return [
			'traffic_logger' => 'ICWP_WPSF_Processor_TrafficLogger',
		];
	}
}