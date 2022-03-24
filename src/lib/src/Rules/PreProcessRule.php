<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\NoSuchConditionHandlerException;

class PreProcessRule {

	public function run( RuleVO $rule, RulesController $ruleCon ) {
		$rule->all_actions = $this->getAllConditionActions( $rule->conditions );

		foreach ( $rule->all_actions as $action ) {
			try {
				/** @var Base $class */
				$class = $ruleCon->locateConditionHandlerClass( $action );
				if ( empty( $rule->wp_hook ) ) {
					$rule->wp_hook = WPHooksOrder::HOOK_NAME( $class::FindMinimumHook() );
				}
			}
			catch ( NoSuchConditionHandlerException $e ) {
			}
		}
	}

	/**
	 * This is recursive and essentially allows for infinite nesting of groups of rules with different logic.
	 */
	private function getAllConditionActions( array $condition ) :array {
		$actions = [];

		if ( isset( $condition[ 'group' ] ) ) {
			$actions = $this->getAllConditionActions( $condition[ 'group' ] );
		}
		else {
			foreach ( $condition as $subCondition ) {
				if ( isset( $subCondition[ 'group' ] ) ) {
					$actions = array_merge( $actions, $this->getAllConditionActions( $subCondition[ 'group' ] ) );
				}
				else {
					$actions[] = $subCondition[ 'action' ];
				}
			}
		}
		return array_unique( $actions );
	}
}