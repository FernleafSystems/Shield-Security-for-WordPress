<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib;

/**
 * Class ICWP_WPSF_Processor_Traffic
 */
class ICWP_WPSF_Processor_Traffic extends Modules\BaseShield\ShieldProcessor {

	public function run() {
		/** @var Modules\Traffic\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isTrafficLoggerEnabled() ) {
			( new Lib\Logger() )
				->setMod( $this->getMod() )
				->run();
			( new Lib\Limit\Limiter() )
				->setMod( $this->getMod() )
				->run();
		}
	}
}