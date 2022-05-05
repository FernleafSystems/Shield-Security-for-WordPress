<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\{
	AttemptToAccessNonExistingRuleException,
	NoConditionActionDefinedException,
	NoResponseActionDefinedException,
	NoSuchConditionHandlerException,
	NoSuchResponseHandlerException
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors\{
	ConditionsProcessor,
	ResponseProcessor
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses\EventFire;

class RulesController {

	use ExecOnce;
	use PluginCronsConsumer;
	use PluginControllerConsumer;

	/**
	 * @var RuleVO[]
	 */
	private $rules;

	/**
	 * @var RulesStorageHandler
	 */
	private $storageHandler;

	public $processComplete;

	public function __construct() {
		$this->processComplete = false;
		$this->storageHandler = ( new RulesStorageHandler() )->setRulesCon( $this );
	}

	protected function run() {

		// Rebuild the rules upon upgrade or settings change
		if ( $this->getCon()->cfg->rebuilt ) {
			$this->storageHandler->buildAndStore();
		}

		// Rebuild the rules when configuration is updated
		add_action( $this->getCon()->prefix( 'pre_options_store' ), function () {
			$this->storageHandler->buildAndStore();
		} );

		// Rebuild the rules every hour
		$this->setupCronHooks();
	}

	public function renderSummary() :string {
		return ( new Render\RenderSummary() )
			->setCon( $this->getCon() )
			->setRulesCon( $this )
			->render();
	}

	public function runHourlyCron() {
		$this->storageHandler->buildAndStore();
	}

	public function getRulesResultsSummary() :array {
		return array_map(
			function ( $rule ) {
				return $rule->result;
			},
			$this->getRules()
		);
	}

	public function isRulesEngineReady() :bool {
		return !empty( $this->getRules() );
	}

	public function processRules() {
		if ( !$this->processComplete && $this->isRulesEngineReady() ) {

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
				}, PHP_INT_MIN );
			}

			$this->processComplete = true;

			add_action( $this->getCon()->prefix( 'plugin_shutdown' ), function () {
//				error_log( var_export( $this->getRulesResultsSummary(), true ) );
			} );
		}
	}

	private function processRule( RuleVO $rule ) {
		$conditionPro = ( new ConditionsProcessor( $rule, $this ) )->setCon( $this->getCon() );
		if ( !isset( $rule->result ) ) {
			$rule->result = $conditionPro->runAllRuleConditions();
			if ( $rule->result ) {
				( new ResponseProcessor( $rule, $this, $conditionPro->getConsolidatedMeta() ) )->run();
			}
		}
	}

	/**
	 * @throws AttemptToAccessNonExistingRuleException
	 */
	public function getRule( string $slug ) :RuleVO {
		$rules = $this->getRules();
		if ( !isset( $rules[ $slug ] ) ) {
			throw new AttemptToAccessNonExistingRuleException( sprintf( 'Rule "%s" does not exist', $slug ) );
		}
		return $rules[ $slug ];
	}

	/**
	 * @return RuleVO[]
	 */
	public function getRules() :array {
		if ( !isset( $this->rules ) ) {
			try {
				$this->rules = array_map(
					function ( $rule ) {
						return ( new RuleVO() )->applyFromArray( $rule );
					},
					( new RulesStorageHandler() )
						->setRulesCon( $this )
						->loadRules()[ 'rules' ]
				);
			}
			catch ( \Exception $e ) {
				$this->rules = [];
			}
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
	 * @throws NoConditionActionDefinedException
	 * @throws NoSuchConditionHandlerException
	 */
	public function getConditionHandler( array $condition ) :Conditions\Base {
		if ( empty( $condition[ 'condition' ] ) ) {
			throw new NoConditionActionDefinedException( 'No Condition Handler available for: '.var_export( $condition, true ) );
		}
		$class = $this->locateConditionHandlerClass( $condition[ 'condition' ] );
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
		if ( empty( $response[ 'response' ] ) ) {
			throw new NoResponseActionDefinedException( 'No Response Handler available for: '.var_export( $response, true ) );
		}
		$theResponseClass = $this->locateResponseHandlerClass( $response[ 'response' ] );
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