<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\RulesController;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\RuleVO;

class ResponseProcessor extends BaseProcessor {

	/**
	 * @var array
	 */
	private $triggerMetaData;

	public function __construct( RuleVO $rule, RulesController $controller, array $triggerMetaData ) {
		parent::__construct( $rule, $controller );
		$this->triggerMetaData = $triggerMetaData;
	}

	public function run() {
		foreach ( $this->rule->responses as $response ) {
			try {
				$this->controller->getResponseHandler( $response )
								 ->setConditionTriggerMeta( $this->triggerMetaData )
								 ->run();
			}
			catch ( Exceptions\NoResponseActionDefinedException $e ) {
			}
			catch ( Exceptions\NoSuchResponseHandlerException $e ) {
			}
		}
	}
}