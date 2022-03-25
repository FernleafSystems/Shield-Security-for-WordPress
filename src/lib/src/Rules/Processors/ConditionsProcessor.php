<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions;

class ConditionsProcessor extends BaseProcessor {

	private $consolidatedMeta = [];

	public function getConsolidatedMeta() :array {
		return array_filter( $this->consolidatedMeta );
	}

	public function run() :bool {
		return $this->processConditionGroup(
			$this->rule->conditions[ 'group' ],
			( $condition[ 'logic' ] ?? 'AND' ) === 'AND'
		);
	}

	/**
	 * This is recursive and essentially allows for infinite nesting of groups of rules with different logic.
	 */
	private function processConditionGroup( array $condition, $isLogicAnd = true ) :bool {
		$finalMatch = null;

		foreach ( $condition as $subCondition ) {

			if ( isset( $subCondition[ 'group' ] ) ) {
				$matched = $this->processConditionGroup( $subCondition[ 'group' ], ( $subCondition[ 'logic' ] ?? 'AND' ) === 'AND' );
			}
			else {
				$matched = false;
				try {
					$handler = $this->controller->getConditionHandler( $subCondition );
					$matched = $handler->run();
					if ( $subCondition[ 'invert_match' ] ?? false ) {
						$matched = !$matched;
					}

					$this->consolidatedMeta[ $subCondition[ 'action' ] ] = $handler->getConditionTriggerMetaData();
				}
				catch ( Exceptions\NoSuchConditionHandlerException $e ) {
					error_log( $e->getMessage() );
				}
				catch ( Exceptions\NoConditionActionDefinedException $e ) {
					error_log( $e->getMessage() );
				}
			}

			if ( is_null( $finalMatch ) ) {
				$finalMatch = $matched;
				continue;
			}

			if ( $isLogicAnd ) {
				$finalMatch = $finalMatch && $matched;
			}
			else {
				$finalMatch = $finalMatch || $matched;
			}

			if ( $isLogicAnd && !$finalMatch ) {
				break;
			}
		}

		return $finalMatch;
	}

	private function isStopOnFirst() :bool {
		return in_array( 'stop_on_first', $this->rule->flags );
	}
}