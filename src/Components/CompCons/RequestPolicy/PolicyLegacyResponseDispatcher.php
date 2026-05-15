<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors\ResponseProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\RuleVO;

class PolicyLegacyResponseDispatcher {

	use PluginControllerConsumer;

	public function updateIpLastAccess( RuleVO $rule ) :void {
		$this->dispatch( $rule, [
			[
				'response' => Responses\UpdateIpRuleLastAccessAt::class,
				'params'   => [],
			],
		] );
	}

	public function dispatch( RuleVO $rule, array $responses ) :void {
		if ( empty( $responses ) ) {
			return;
		}

		( new ResponseProcessor( $rule ) )
			->setThisRequest( self::con()->this_req )
			->runResponsesOnly( $responses );
	}
}
