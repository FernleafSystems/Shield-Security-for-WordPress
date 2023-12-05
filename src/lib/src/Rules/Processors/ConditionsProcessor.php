<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\{
	AttemptToAccessNonExistingRuleException,
	NoConditionActionDefinedException,
	NoSuchConditionHandlerException,
	RuleNotYetRunException
};

/**
 * @deprecated 18.5.8
 */
class ConditionsProcessor extends BaseProcessor {

	private $consolidatedMeta = [];

	public function getConsolidatedMeta() :array {
		return \array_filter( $this->consolidatedMeta );
	}

	/**
	 * If there are no conditions, then we're 'true'
	 */
	public function runAllRuleConditions() :bool {
		$condition = true;
		$conditions = $this->rule->conditions ?? [];
		if ( !empty( $conditions[ 'conditions' ] ) ) {
			$processor = new ProcessConditions( $conditions[ 'conditions' ], $conditions[ 'logic' ] ?? Constants::LOGIC_AND );
			$condition = $processor->process();
			$this->consolidatedMeta = $processor->getConsolidatedMeta();
		}
		return $condition;
	}

	/**
	 * This is recursive and essentially allows for infinite nesting of groups of rules with different logic.
	 * @deprecated 18.5.8
	 */
	private function processConditionGroup( array $conditionGroup, $isLogicAnd = true ) :bool {
		$finalMatch = null;

		foreach ( $conditionGroup as $subCondition ) {

			if ( isset( $subCondition[ 'conditions' ] ) ) {
				$matched = $this->processConditionGroup( $subCondition[ 'conditions' ], ( $subCondition[ 'logic' ] ?? 'AND' ) === 'AND' );
			}
			elseif ( isset( $subCondition[ 'rule' ] ) ) {
				try {
					$matched = $this->lookupPreviousRule( $subCondition[ 'rule' ] );
					if ( $subCondition[ 'invert_match' ] ?? false ) {
						$matched = !$matched;
					}
				}
				catch ( RuleNotYetRunException|AttemptToAccessNonExistingRuleException $e ) {
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
					$this->consolidatedMeta[ $subCondition[ 'conditions' ] ] = $handler->getConditionTriggerMetaData();
				}
				catch ( NoSuchConditionHandlerException|NoConditionActionDefinedException $e ) {
					error_log( $e->getMessage() );
					continue;
				}
			}

			if ( \is_null( $finalMatch ) ) {
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
	 * @throws AttemptToAccessNonExistingRuleException
	 * @throws RuleNotYetRunException
	 */
	private function lookupPreviousRule( string $rule ) :bool {
		$result = self::con()->rules->getRule( $rule )->result;
		if ( \is_null( $result ) ) {
			throw new RuleNotYetRunException( 'Rule not yet run: '.$rule );
		}
		return $result;
	}
}