<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\NoSuchConditionHandlerException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\WPHooksOrder;

class PreProcessRule extends BaseProcessor {

	public function run() {
		$this->rule->all_actions = $this->getAllConditionActions( $this->rule->conditions[ 'group' ] );
		$this->findMinimumHook();
	}

	private function findMinimumHook() {
		$minimumHook = WPHooksOrder::NONE;
		foreach ( $this->rule->all_actions as $action ) {
			try {
				/** @var Base $class */
				$class = $this->controller->locateConditionHandlerClass( $action );
				$minimumHook = max( $minimumHook, $class::FindMinimumHook() );
			}
			catch ( NoSuchConditionHandlerException $e ) {
				error_log( $e->getMessage() );
			}
		}
		$this->rule->wp_hook = WPHooksOrder::HOOK_NAME( $minimumHook );
	}

	/**
	 * This is recursive and essentially allows for infinite nesting of groups of rules with different logic.
	 */
	private function getAllConditionActions( array $conditions ) :array {
		$actions = [];

		foreach ( $conditions as $subCondition ) {
			if ( isset( $subCondition[ 'group' ] ) ) {
				$actions = array_merge( $actions, $this->getAllConditionActions( $subCondition[ 'group' ] ) );
			}
			elseif ( !empty( $subCondition[ 'action' ] ) ) {
				$actions[] = $subCondition[ 'action' ];
			}
		}

		return array_unique( $actions );
	}
}