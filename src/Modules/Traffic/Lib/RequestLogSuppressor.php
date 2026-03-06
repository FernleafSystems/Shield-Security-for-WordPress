<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogSuppressions\{
	BaseRule,
	Context,
	Rules
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class RequestLogSuppressor {

	use PluginControllerConsumer;

	public function shouldSuppress() :bool {
		$context = new Context( self::con()->this_req );
		$shouldSuppress = false;

		foreach ( $this->enumRules() as $rule ) {
			if ( $rule->matches( $context ) ) {
				$shouldSuppress = true;
				break;
			}
		}

		return $shouldSuppress;
	}

	/**
	 * @return BaseRule[]
	 */
	private function enumRules() :array {
		return [
			new Rules\ShieldLiveMonitorAjax(),
			new Rules\LoggedInUsersMeRest(),
		];
	}
}
