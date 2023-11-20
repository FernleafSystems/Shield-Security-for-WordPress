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
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\Builder;
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
		if ( self::con()->cfg->rebuilt ) {
			$this->buildAndStore();
		}

		// Rebuild the rules when configuration is updated
		add_action( self::con()->prefix( 'after_pre_options_store' ), function ( $cfgChanged ) {
			if ( $cfgChanged ) {
				$this->buildAndStore();
			}
		} );

		// Rebuild the rules every hour
		$this->setupCronHooks();
	}

	public function runHourlyCron() {
		$this->buildAndStore();
	}

	public function buildAndStore() {
		$this->storageHandler->store(
			( new Builder() )
				->setRulesCon( $this )
				->run()
		);
	}

	public function getRulesResultsSummary() :array {
		return \array_map(
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

			$hooks = [];
			foreach ( $this->getRules() as $rule ) {
				$hook = $rule->wp_hook;
				if ( !empty( $hook ) ) {
					$priority = $rule->wp_hook_priority;
					$hooks[ $hook ] = isset( $hooks[ $hook ] ) ? \min( $priority, $hooks[ $hook ] ) : $priority;
				}
			}

			foreach ( $hooks as $wpHook => $priority ) {
				add_action( $wpHook, function () use ( $wpHook ) {
					foreach ( $this->getRulesForHook( $wpHook ) as $rule ) {
						$this->processRule( $rule );
					}
				}, $priority );
			}

			$this->processComplete = true;

			add_action( self::con()->prefix( 'plugin_shutdown' ), function () {
//				error_log( var_export( $this->getRulesResultsSummary(), true ) );
			} );
		}
	}

	private function processRule( RuleVO $rule ) {
		$conditionPro = new ConditionsProcessor( $rule, $this );
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
				$this->rules = \array_map(
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
		return \array_filter( $this->getRules(), function ( $rule ) use ( $hook ) {
			return $rule->wp_hook === $hook;
		} );
	}

	/**
	 * @return Conditions\Base|mixed
	 * @throws NoConditionActionDefinedException
	 * @throws NoSuchConditionHandlerException
	 */
	public function getConditionHandler( array $condition ) {
		if ( empty( $condition[ 'condition' ] ) ) {
			throw new NoConditionActionDefinedException( 'No Condition Handler available for: '.var_export( $condition, true ) );
		}
		$class = $this->locateConditionHandlerClass( $condition[ 'condition' ] );
		return new $class( $condition[ 'params' ] ?? [] );
	}

	/**
	 * @throws NoSuchConditionHandlerException
	 */
	public function locateConditionHandlerClass( string $condition ) :string {
		$theHandlerClass = sprintf( '%s\\Conditions\\%s', __NAMESPACE__,
			\implode( '', \array_map( '\ucfirst', \explode( '_', $condition ) ) ) );
		if ( !\class_exists( $theHandlerClass ) ) {
			throw new NoSuchConditionHandlerException( 'No such Condition Handler Class for: '.$theHandlerClass );
		}
		return $theHandlerClass;
	}

	public function getDefaultEventFireResponseHandler() :Responses\EventFire {
		return new EventFire( [] );
	}

	/**
	 * @return Responses\Base|mixed
	 * @throws NoResponseActionDefinedException
	 * @throws NoSuchResponseHandlerException
	 */
	public function getResponseHandler( array $response ) {
		if ( empty( $response[ 'response' ] ) ) {
			throw new NoResponseActionDefinedException( 'No Response Handler available for: '.var_export( $response, true ) );
		}
		$theResponseClass = $this->locateResponseHandlerClass( $response[ 'response' ] );
		return new $theResponseClass( $response[ 'params' ] ?? [] );
	}

	/**
	 * @throws NoSuchResponseHandlerException
	 */
	public function locateResponseHandlerClass( string $response ) :string {
		$theHandlerClass = sprintf( '%s\\Responses\\%s', __NAMESPACE__,
			\implode( '', \array_map( '\ucfirst', \explode( '_', $response ) ) ) );
		if ( !\class_exists( $theHandlerClass ) ) {
			throw new NoSuchResponseHandlerException( 'No Response Handler Class for: '.$theHandlerClass );
		}
		return $theHandlerClass;
	}
}