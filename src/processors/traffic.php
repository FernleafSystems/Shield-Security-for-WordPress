<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

/**
 * Class ICWP_WPSF_Processor_Traffic
 */
class ICWP_WPSF_Processor_Traffic extends ICWP_WPSF_Processor_BaseWpsf {

	public function run() {
		$this->getProcessorLogger()->run();
	}

	/**
	 * Not fully tested- aim for 8.1 release
	 */
	public function onWpInit() {
		/** @var Traffic\Options $oOpts */
		$oOpts = $this->getMod()->getOptions();
		if ( false && $oOpts->isTrafficLimitEnabled() ) {
			( new Traffic\Limiter\Limiter() )
				->setMod( $this->getMod() )
				->run();
		}
	}

	/**
	 * @return ICWP_WPSF_Processor_TrafficLogger|mixed
	 */
	public function getProcessorLogger() {
		return $this->getSubPro( 'logger' );
	}

	/**
	 * @return array
	 */
	protected function getSubProMap() {
		return [
			'logger'  => 'ICWP_WPSF_Processor_TrafficLogger',
			'limiter' => 'ICWP_WPSF_Processor_TrafficLogger',
		];
	}
}