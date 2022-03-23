<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions;

class ConditionsProcessor extends BaseProcessor {

	private $consolidatedMeta = [];

	public function getConsolidatedMeta() :array {
		return array_filter( $this->consolidatedMeta );
	}

	public function run() {
		$matched = false;
		foreach ( $this->rule->conditions as $condition ) {
			try {
				$handler = $this->controller->getConditionHandler( $condition );
				$matched = $handler->run();
				if ( $handler->run() ) {
					$matched = true;
					$this->consolidatedMeta[ $condition ] = $handler->getConditionTriggerMetaData();

					if ( $this->isStopOnFirst() ) {
						break;
					}
				}
			}
			catch ( Exceptions\NoSuchConditionHandlerException $e ) {
			}
		}
		return $matched;
	}

	private function isStopOnFirst() :bool {
		return in_array( 'stop_on_first', $this->rule->flags );
	}
}