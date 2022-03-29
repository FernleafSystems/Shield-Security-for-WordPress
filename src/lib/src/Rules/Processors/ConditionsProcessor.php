<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions;

class ConditionsProcessor extends BaseProcessor {

	private $consolidatedMeta = [];

	public function getConsolidatedMeta() :array {
		return array_filter( $this->consolidatedMeta );
	}

	public function runAllRuleConditions() :bool {
		$thisReq = $this->controller->getCon()->req;
		if ( !isset( $thisReq->rules_conditions_results[ $this->rule->slug ] ) ) {
			$thisReq->rules_conditions_results[ $this->rule->slug ] = $this->processConditionGroup(
				$this->rule->conditions[ 'group' ],
				( $condition[ 'logic' ] ?? 'AND' ) === 'AND'
			);
		}
		return $thisReq->rules_conditions_results[ $this->rule->slug ];
	}

	/**
	 * This is recursive and essentially allows for infinite nesting of groups of rules with different logic.
	 */
	private function processConditionGroup( array $conditionGroup, $isLogicAnd = true ) :bool {
		$finalMatch = null;

		foreach ( $conditionGroup as $subCondition ) {

			if ( isset( $subCondition[ 'group' ] ) ) {
				$matched = $this->processConditionGroup( $subCondition[ 'group' ], ( $subCondition[ 'logic' ] ?? 'AND' ) === 'AND' );
			}
			elseif ( isset( $subCondition[ 'rule' ] ) ) {
				try {
					$matched = $this->lookupPreviousRule( $subCondition[ 'rule' ] );
					if ( $subCondition[ 'invert_match' ] ?? false ) {
						$matched = !$matched;
					}
				}
				catch ( Exceptions\RuleNotYetRunException $e ) {
					return false;
				}
			}
			else {
				try {
					$handler = $this->controller->getConditionHandler( $subCondition );
					$matched = $handler->setRule( $this->rule )
									   ->run();
					if ( $subCondition[ 'invert_match' ] ?? false ) {
						$matched = !$matched;
					}
					$this->consolidatedMeta[ $subCondition[ 'action' ] ] = $handler->getConditionTriggerMetaData();
				}
				catch ( Exceptions\NoSuchConditionHandlerException $e ) {
					error_log( $e->getMessage() );
					continue;
				}
				catch ( Exceptions\NoConditionActionDefinedException $e ) {
					error_log( $e->getMessage() );
					continue;
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

		return (bool)$finalMatch;
	}

	/**
	 * @throws Exceptions\RuleNotYetRunException
	 */
	private function lookupPreviousRule( string $rule ) :bool {
		$result = $this->getCon()
					  ->req
					  ->rules_conditions_results[ $rule ] ?? null;
		if ( is_null( $result ) ) {
			throw new Exceptions\RuleNotYetRunException( 'Rule not yet run: '.$rule );
		}
		return $result;
	}

	private function isStopOnFirst() :bool {
		return in_array( 'stop_on_first', $this->rule->flags );
	}
}