<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class RuleProcessor {

	use PluginControllerConsumer;

	/**
	 * @var RuleVO
	 */
	private $rule;

	/**
	 * @var RulesController
	 */
	private $controller;

	public function __construct( RuleVO $rule, RulesController $controller ) {
		$this->rule = $rule;
		$this->controller = $controller;
	}

	public function run() {
		$triggered = false;
		foreach ( $this->rule->conditions as $trigger ) {
			try {
				$triggered = $this->controller->getConditionHandler( $trigger )->run();
				if ( $triggered ) {
					if ( $this->isStopOnFirst() ) {
						break;
					}
				}
			}
			catch ( Exceptions\NoSuchConditionHandlerException $e ) {
			}
		}
	}

	private function isStopOnFirst() :bool {
		return in_array( 'stop_on_first', $this->rule->flags );
	}
}