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
				if ( $condition[ 'invert_match' ] ?? false ) {
					$matched = !$matched;
				}

				if ( $matched ) {
					$matched = true;
					$this->consolidatedMeta[ $condition[ 'action' ] ] = $handler->getConditionTriggerMetaData();

					if ( $this->isStopOnFirst() ) {
						break;
					}
				}
				else {
					$matched = false;
					break;
				}
			}
			catch ( Exceptions\NoSuchConditionHandlerException $e ) {
				error_log( $e->getMessage() );
			}
			catch ( Exceptions\NoConditionActionDefinedException $e ) {
				error_log( $e->getMessage() );
			}
		}

		return $matched;
	}

	private function isStopOnFirst() :bool {
		return in_array( 'stop_on_first', $this->rule->flags );
	}
}