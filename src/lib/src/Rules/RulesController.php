<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\NoResponseActionDefinedException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\NoSuchConditionHandlerException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\NoSuchResponseHandlerException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors\ConditionsProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors\ResponseProcessor;
use FernleafSystems\Wordpress\Services\Services;

class RulesController {

	use ExecOnce;
	use PluginControllerConsumer;

	/**
	 * @var RuleVO[]
	 */
	private $rules;

	protected function canRun() :bool {
		return Services::Data()->getPhpVersionIsAtLeast( '7.4' ) && !$this->getCon()->req->rules_completed;
	}

	protected function run() {
		foreach ( $this->getImmediateRules() as $rule ) {
			$this->processRule( $rule );
		}

		$allHooks = array_unique( array_filter( array_map( function ( $rule ) {
			return $rule->wp_hook;
		}, $this->getRules() ) ) );
		foreach ( $allHooks as $wpHook ) {
			add_action( $wpHook, function () use ( $wpHook ) {
				foreach ( $this->getRulesForHook( $wpHook ) as $rule ) {
					$this->processRule( $rule );
				}
			} );
		}

		$this->getCon()->req->rules_completed = true;
	}

	private function processRule( RuleVO $rule ) {
		$conditionPro = new ConditionsProcessor( $rule, $this );
		if ( $conditionPro->run() ) {
			$responsePro = new ResponseProcessor( $rule, $this, $conditionPro->getConsolidatedMeta() );
			$responsePro->run();
		}
	}

	protected function getRules() :array {
		if ( !isset( $this->rules ) ) {
			$this->rules = array_map(
				function ( $rule ) {
					return ( new RuleVO() )->applyFromArray( $rule );
				},
				json_decode( Services::WpFs()->getFileContent( path_join( __DIR__, 'rules.json' ) ), true )[ 'rules' ]
			);
		}
		return $this->rules;
	}

	/**
	 * @return RuleVO[]
	 */
	private function getImmediateRules() :array {
		return $this->getRulesForHook( '' );
	}

	/**
	 * @return RuleVO[]
	 */
	private function getRulesForHook( string $hook ) :array {
		return array_filter( $this->getRules(), function ( $rule ) use ( $hook ) {
			return $rule->wp_hook === $hook;
		} );
	}

	/**
	 * @throws NoSuchConditionHandlerException
	 */
	public function getConditionHandler( string $condition ) :Conditions\Base {
		$theHandlerClass = null;
		foreach ( $this->enumConditionHandlers() as $class ) {
			if ( $condition === constant( sprintf( '%s::%s', $class, 'SLUG' ) ) ) {
				$theHandlerClass = $class;
				break;
			}
		}
		if ( empty( $theHandlerClass ) ) {
			throw new NoSuchConditionHandlerException( 'No Condition Handler available for: '.$condition );
		}
		/** @var Conditions\Base $d */
		$d = new $theHandlerClass();
		return $d->setCon( $this->getCon() );
	}

	/**
	 * @throws NoResponseActionDefinedException
	 * @throws NoSuchResponseHandlerException
	 */
	public function getResponseHandler( array $response ) :Responses\Base {
		$theHandlerClass = null;

		if ( empty( $response[ 'action' ] ) ) {
			throw new NoResponseActionDefinedException( 'No Response Handler available for: '.var_export( $response, true ) );
		}

		foreach ( $this->enumResponseHandlers() as $class ) {
			if ( $response[ 'action' ] === constant( sprintf( '%s::%s', $class, 'SLUG' ) ) ) {
				$theHandlerClass = $class;
				break;
			}
		}
		if ( empty( $theHandlerClass ) ) {
			throw new NoSuchResponseHandlerException( 'No Response Handler available for: '.$response[ 'action' ] );
		}
		/** @var Responses\Base $d */
		$d = new $theHandlerClass();
		return $d->setCon( $this->getCon() );
	}

	protected function enumConditionHandlers() :array {
		return [
			Conditions\IsFakeWebCrawler::class,
			Conditions\IsServerLoopback::class,
			Conditions\IsTrustedBot::class,
			Conditions\IsXmlrpc::class,
			Conditions\MatchRequestIP::class,
			Conditions\MatchRequestIPIdentity::class,
			Conditions\MatchRequestPath::class,
			Conditions\MatchUserAgent::class,
		];
	}

	protected function enumResponseHandlers() :array {
		return [
//			Responses\IsFakeWebCrawler::class,
//			Responses\IsServerLoopback::class,
			Responses\IsTrustedBot::class,
//			Responses\IsXmlrpc::class,
//			Responses\MatchRequestIP::class,
//			Responses\MatchRequestIPIdentity::class,
//			Responses\MatchRequestPath::class,
//			Responses\MatchUserAgent::class,
		];
	}
}