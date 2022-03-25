<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\Builder;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\NoConditionActionDefinedException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\NoResponseActionDefinedException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\NoSuchConditionHandlerException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\NoSuchResponseHandlerException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors\ConditionsProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors\PreProcessRule;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors\ResponseProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses\EventFire;
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
		$this->processRules();
		add_action( $this->getCon()->prefix( 'pre_options_store' ), fn() => $this->buildRules() );
	}

	private function buildRules() {
		( new Builder() )
			->setCon( $this->getCon() )
			->run( $this );
	}

	private function processRules() {
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
		if ( $conditionPro->runAllRuleConditions() ) {
			$responsePro = new ResponseProcessor( $rule, $this, $conditionPro->getConsolidatedMeta() );
			$responsePro->run();
		}
	}

	public function storeRules( array $rules ) :bool {
		return (bool)Services::WpFs()->putFileContent( $this->getPathToRules(), json_encode( [
			'rules' => array_map( fn( RuleVO $rule ) => $rule->getRawData(), $rules )
		] ) );
	}

	protected function getRules() :array {
		if ( !isset( $this->rules ) ) {
			$this->rules = array_map(
				function ( $rule ) {
					$rule = ( new RuleVO() )->applyFromArray( $rule );
					( new PreProcessRule( $rule, $this ) )->run();
					return $rule;
				},
				json_decode( Services::WpFs()->getFileContent( $this->getPathToRules() ), true )[ 'rules' ]
			);
		}
		return $this->rules;
	}

	private function getPathToRules() :string {
		return path_join( __DIR__, 'rules.json' );
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
	 * @throws NoConditionActionDefinedException
	 * @throws NoSuchConditionHandlerException
	 */
	public function getConditionHandler( array $condition ) :Conditions\Base {
		if ( empty( $condition[ 'action' ] ) ) {
			throw new NoConditionActionDefinedException( 'No Condition Handler available for: '.var_export( $condition, true ) );
		}
		$class = $this->locateConditionHandlerClass( $condition[ 'action' ] );
		/** @var Conditions\Base $cond */
		$cond = new $class( $condition[ 'params' ] ?? [] );
		return $cond->setCon( $this->getCon() );
	}

	/**
	 * @throws NoSuchConditionHandlerException
	 */
	public function locateConditionHandlerClass( string $condition ) :string {
		$theHandlerClass = sprintf( '%s\\Conditions\\%s', __NAMESPACE__,
			implode( '', array_map( 'ucfirst', explode( '_', $condition ) ) ) );
		if ( !class_exists( $theHandlerClass ) ) {
			throw new NoSuchConditionHandlerException( 'No such Condition Handler Class for: '.$theHandlerClass );
		}
		return $theHandlerClass;
	}

	public function getDefaultEventFireResponseHandler() :Responses\EventFire {
		/** @var Responses\Base $d */
		return ( new EventFire( [] ) )->setCon( $this->getCon() );
	}

	/**
	 * @throws NoResponseActionDefinedException
	 * @throws NoSuchResponseHandlerException
	 */
	public function getResponseHandler( array $response ) :Responses\Base {
		if ( empty( $response[ 'action' ] ) ) {
			throw new NoResponseActionDefinedException( 'No Response Handler available for: '.var_export( $response, true ) );
		}
		$theResponseClass = $this->locateResponseHandlerClass( $response[ 'action' ] );
		/** @var Responses\Base $d */
		$d = new $theResponseClass( $response[ 'params' ] ?? [] );
		return $d->setCon( $this->getCon() );
	}

	/**
	 * @throws NoSuchResponseHandlerException
	 */
	public function locateResponseHandlerClass( string $response ) :string {
		$theHandlerClass = sprintf( '%s\\Responses\\%s', __NAMESPACE__,
			implode( '', array_map( 'ucfirst', explode( '_', $response ) ) ) );
		if ( !class_exists( $theHandlerClass ) ) {
			throw new NoSuchResponseHandlerException( 'No Response Handler Class for: '.$theHandlerClass );
		}
		return $theHandlerClass;
	}
}