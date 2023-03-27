<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\{
	NoResponseActionDefinedException,
	NoSuchResponseHandlerException
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\RulesController;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\RuleVO;

class ResponseProcessor extends BaseProcessor {

	/**
	 * @var array
	 */
	private $triggerMetaData;

	public function __construct( RuleVO $rule, RulesController $rulesCon, array $triggerMetaData ) {
		parent::__construct( $rule, $rulesCon );
		$this->triggerMetaData = $triggerMetaData;
	}

	public function run() {
		$eventFireResponseProcessed = false;
		foreach ( $this->rule->responses as $response ) {
			try {
				$this->rulesCon->getResponseHandler( $response )
							   ->setConditionTriggerMeta( $this->triggerMetaData )
							   ->setRule( $this->rule )
							   ->run();
				if ( $response[ 'response' ] === 'event_fire' ) {
					$eventFireResponseProcessed = true;
				}
			}
			catch ( NoResponseActionDefinedException|NoSuchResponseHandlerException $e ) {
				error_log( $e->getMessage() );
			}
		}

		// We always fire the default event if an event wasn't fired already
		if ( !$eventFireResponseProcessed ) {
			$this->rulesCon->getDefaultEventFireResponseHandler()
						   ->setConditionTriggerMeta( $this->triggerMetaData )
						   ->setRule( $this->rule )
						   ->run();
		}
	}
}