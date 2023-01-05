<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions;

class ConditionsProcessor extends BaseProcessor {

	private $consolidatedMeta = [];

	public function getConsolidatedMeta() :array {
		return array_filter( $this->consolidatedMeta );
	}

	public function runAllRuleConditions() :bool {
		// If there are no conditions, then we're 'true'
		return empty( $this->rule->conditions[ 'group' ] ) ||
			   $this->processConditionGroup(
				   $this->rule->conditions[ 'group' ],
				   ( $this->rule->conditions[ 'logic' ] ?? 'AND' ) === 'AND'
			   );
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
					error_log( $e->getMessage() );
					return false;
				}
				catch ( Exceptions\AttemptToAccessNonExistingRuleException $e ) {
					error_log( $e->getMessage() );
					return false;
				}
			}
			else {
				try {
					$handler = $this->rulesCon->getConditionHandler( $subCondition );
					$matched = $handler->setRule( $this->rule )
									   ->run();
					if ( $subCondition[ 'invert_match' ] ?? false ) {
						$matched = !$matched;
					}
					$this->consolidatedMeta[ $subCondition[ 'condition' ] ] = $handler->getConditionTriggerMetaData();
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
			}

			if ( $isLogicAnd ) {
				$finalMatch = $finalMatch && $matched;
				if ( !$finalMatch ) {
					break;
				}
			}
			else {
				$finalMatch = $finalMatch || $matched;
			}
		}

		return (bool)$finalMatch;
	}

	/**
	 * @throws Exceptions\AttemptToAccessNonExistingRuleException
	 * @throws Exceptions\RuleNotYetRunException
	 */
	private function lookupPreviousRule( string $rule ) :bool {
		$result = $this->rulesCon->getRule( $rule )->result;
		if ( is_null( $result ) ) {
			throw new Exceptions\RuleNotYetRunException( 'Rule not yet run: '.$rule );
		}
		return $result;
	}
}