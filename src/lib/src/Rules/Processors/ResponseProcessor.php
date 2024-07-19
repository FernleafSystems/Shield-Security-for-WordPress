<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Request\ThisRequestConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Exceptions\NoResponseActionDefinedException,
	Exceptions\NoSuchResponseHandlerException,
	Exceptions\ParametersException,
	Responses,
	RuleVO,
	Utility
};

class ResponseProcessor {

	use PluginControllerConsumer;
	use ThisRequestConsumer;

	/**
	 * @var RuleVO
	 */
	protected $rule;

	public function __construct( RuleVO $rule ) {
		$this->rule = $rule;
	}

	public function run() {
		foreach ( $this->rule->responses as $respDef ) {
			try {
				$responseClass = $respDef[ 'response' ] ?? null;
				if ( empty( $responseClass ) ) {
					throw new NoResponseActionDefinedException( 'No Response Handler defined for: '.var_export( $respDef, true ) );
				}
				if ( !\class_exists( $responseClass ) ) {
					throw new NoSuchResponseHandlerException( 'No Such Response Handler Class: '.$responseClass );
				}

				$params = $respDef[ 'params' ] ?? [];
				/** @var Responses\Base $responseClass */
				$response = new $responseClass();
				$response->setThisRequest( $this->req )
						 ->setRule( $this->rule )
						 ->setParams( $params );
				( new Utility\VerifyParams() )->verifyParams( $params, $response->getParamsDef() );
				$this->execResponse( $response );
			}
			catch ( NoResponseActionDefinedException|NoSuchResponseHandlerException $e ) {
				error_log( $e->getMessage() );
			}
			catch ( ParametersException|\Exception $e ) {
//				error_log( $e->getMessage() );
			}
		}

		try {
			// We always fire the default event
			$defaultEventResponse = new Responses\EventFireDefault();
			$defaultEventResponse->setThisRequest( $this->req )
								 ->setRule( $this->rule )
								 ->setParams( [
									 'rule_slug' => $this->rule->slug
								 ] );
			$this->execResponse( $defaultEventResponse );
		}
		catch ( \Exception $e ) {
		}
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