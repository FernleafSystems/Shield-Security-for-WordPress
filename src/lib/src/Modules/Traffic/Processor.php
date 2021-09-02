<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;

class Processor extends Modules\BaseShield\Processor {

	protected function run() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		( new Lib\Limit\Limiter() )
			->setMod( $this->getMod() )
			->execute();
		if ( $opts->isTrafficLoggerEnabled() ) {
		}
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$mod->getRequestLogger()->execute();
	}
}