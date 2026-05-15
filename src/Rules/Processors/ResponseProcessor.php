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
		[ $nonTerminating, $terminating ] = $this->buildResponses( $this->rule->responses );
		$this->dispatchResponses( $nonTerminating, true );

		try {
			// We always fire the default event.
			$defaultEventResponse = new Responses\EventFireDefault();
			$defaultEventResponse->setThisRequest( $this->req )
								 ->setRule( $this->rule )
								 ->setParams( [
									 'rule_slug' => $this->rule->slug
								 ] );
			$this->dispatchResponse( $defaultEventResponse, true );
		}
		catch ( \Exception $e ) {
		}

		$this->dispatchResponses( $terminating, true );
	}

	public function runResponsesOnly( array $responses ) :void {
		[ $nonTerminating, $terminating ] = $this->buildResponses( $responses );
		$this->dispatchResponses( $nonTerminating, false );
		$this->dispatchResponses( $terminating, false );
	}

	/**
	 * @return array{0:array<Responses\Base>,1:array<Responses\Base>}
	 */
	private function buildResponses( array $responses ) :array {
		$nonTerminating = [];
		$terminating = [];
		foreach ( $responses as $respDef ) {
			$response = $this->buildResponse( $respDef );
			if ( $response instanceof Responses\Base ) {
				if ( $response->isTerminating() ) {
					$terminating[] = $response;
				}
				else {
					$nonTerminating[] = $response;
				}
			}
		}
		return [ $nonTerminating, $terminating ];
	}

	private function buildResponse( array $respDef ) :?Responses\Base {
		try {
			$responseClass = $respDef[ 'response' ] ?? null;
			if ( empty( $responseClass ) ) {
				throw new NoResponseActionDefinedException( 'No Response Handler defined for: '.var_export( $respDef, true ) );
			}
			if ( !\class_exists( $responseClass ) ) {
				throw new NoSuchResponseHandlerException( 'No Such Response Handler Class: '.$responseClass );
			}

			$params = $respDef[ 'params' ] ?? [];
			/** @var class-string<Responses\Base> $responseClass */
			$response = new $responseClass();
			$params = ( new Utility\ResponseParamsNormalizer() )->normalize( $responseClass, $params );
			$params = ( new Utility\VerifyParams() )->verifyParams( $params, $response->getParamsDef() );
			return $response->setThisRequest( $this->req )
							->setRule( $this->rule )
							->setParams( $params );
		}
		catch ( NoResponseActionDefinedException|NoSuchResponseHandlerException $e ) {
			error_log( $e->getMessage() );
		}
		catch ( ParametersException|\Exception $e ) {
//			error_log( $e->getMessage() );
		}
		return null;
	}

	/**
	 * @param array<Responses\Base> $responses
	 */
	private function dispatchResponses( array $responses, bool $respectTiming ) :void {
		foreach ( $responses as $response ) {
			$this->dispatchResponse( $response, $respectTiming );
		}
	}

	/**
	 * @param Responses\Base $response
	 */
	private function dispatchResponse( Responses\Base $response, bool $respectTiming ) :void {
		$con = self::con();
		if ( !$respectTiming || $this->rule->immediate_exec_response || did_action( $con->prefix( 'after_run_processors' ) ) ) {
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
