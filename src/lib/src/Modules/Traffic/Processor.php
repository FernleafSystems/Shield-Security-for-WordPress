<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib;

/**
 * Class Processor
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic
 */
class Processor extends Modules\BaseShield\Processor {

	public function run() {
		/** @var Options $opts */
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