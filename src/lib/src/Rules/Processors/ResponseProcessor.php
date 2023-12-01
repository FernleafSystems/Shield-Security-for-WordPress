<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Exceptions\NoResponseActionDefinedException,
	Exceptions\NoSuchResponseHandlerException,
	Responses,
	RuleVO
};

class ResponseProcessor {

	use PluginControllerConsumer;

	/**
	 * @var RuleVO
	 */
	protected $rule;

	/**
	 * @var array
	 */
	private $triggerMetaData;

	public function __construct( RuleVO $rule, array $triggerMetaData ) {
		$this->rule = $rule;
		$this->triggerMetaData = $triggerMetaData;
	}

	public function run() {
		foreach ( $this->rule->responses as $respDef ) {
			try {
				$responseClass = $respDef[ 'response' ] ?? null;
				if ( empty( $responseClass ) ) {
					throw new NoResponseActionDefinedException( 'No Response Handler definied for: '.var_export( $respDef, true ) );
				}
				if ( !\class_exists( $responseClass ) ) {
					throw new NoSuchResponseHandlerException( 'No Such Response Handler Class: '.$responseClass );
				}

				/** @var Responses\Base $response */
				$response = new $responseClass( $response[ 'params' ] ?? [], $this->triggerMetaData );
				$this->execResponse( $response );
			}
			catch ( NoResponseActionDefinedException|NoSuchResponseHandlerException $e ) {
				error_log( $e->getMessage() );
			}
		}

		// We always fire the default event
		$this->execResponse( new Responses\EventFireDefault( [ 'rule_slug' => $this->rule->slug ] ) );
	}

	/**
	 * @param Responses\Base $response
	 */
	private function execResponse( Responses\Base $response ) :void {
		$con = self::con();
		if ( $this->rule->immediate_exec_response || did_action( $con->prefix( 'after_run_processors' ) ) ) {
			try {
				$response->execResponse();
			}
			catch ( \Exception $e ) {
			}
		}
		else {
			add_action( $con->prefix( 'after_run_processors' ), function () use ( $response ) {
				try {
					$response->execResponse();
				}
				catch ( \Exception $e ) {
				}
			} );
		}
	}
}