<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Logging\Processors;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use Monolog\Processor\ProcessorInterface;

class ShieldMetaProcessor implements ProcessorInterface {

	use PluginControllerConsumer;

	/**
	 * @return array
	 */
	public function __invoke( array $record ) {
		$record[ 'extra' ][ 'meta_shield' ] = array_filter( [
			'offense' => $this->getCon()
							  ->getModule_IPs()
							  ->loadOffenseTracker()
							  ->getOffenseCount() > 0 ? 1 : 0,
		] );
		return $record;
	}
}