<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;

/**
 * Class ICWP_WPSF_Processor_Traffic
 */
class ICWP_WPSF_Processor_Traffic extends Modules\BaseShield\ShieldProcessor {

	public function run() {
		$this->getProcessorLogger()->execute();
	}

	/**
	 * Not fully tested- aim for 8.1 release
	 */
	public function onWpInit() {
		/** @var Modules\Traffic\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( false && $oOpts->isTrafficLimitEnabled() ) {
			( new Modules\Traffic\Limiter\Limiter() )
				->setMod( $this->getMod() )
				->run();
		}
	}

	/**
	 * @return \ICWP_WPSF_Processor_TrafficLogger|mixed
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
		];
	}
}