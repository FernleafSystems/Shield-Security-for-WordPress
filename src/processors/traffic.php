<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib;

/**
 * Class ICWP_WPSF_Processor_Traffic
 * @deprecated 10.1
 */
class ICWP_WPSF_Processor_Traffic extends Modules\BaseShield\ShieldProcessor {

	public function run() {
		/** @var Modules\Traffic\Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isTrafficLoggerEnabled() ) {
			( new Lib\Logger() )
				->setMod( $this->getMod() )
				->run();
			( new Lib\Limit\Limiter() )
				->setMod( $this->getMod() )
				->run();
		}
	}
}