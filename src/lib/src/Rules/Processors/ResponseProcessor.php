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
		$eventFireResponseProcessed = false;
		foreach ( $this->rule->responses as $response ) {
			try {
				$this->controller->getResponseHandler( $response )
								 ->setConditionTriggerMeta( $this->triggerMetaData )
								 ->setRule( $this->rule )
								 ->run();
				if ( $response[ 'action' ] === 'event_fire' ) {
					$eventFireResponseProcessed = true;
				}
			}
			catch ( Exceptions\NoResponseActionDefinedException $e ) {
				error_log( $e->getMessage() );
			}
			catch ( Exceptions\NoSuchResponseHandlerException $e ) {
				error_log( $e->getMessage() );
			}
		}

		// We always fire the default event if an event wasn't fired already
		if ( !$eventFireResponseProcessed ) {
			$this->controller->getDefaultEventFireResponseHandler()
							 ->setConditionTriggerMeta( $this->triggerMetaData )
							 ->setRule( $this->rule )
							 ->run();
		}
	}
}