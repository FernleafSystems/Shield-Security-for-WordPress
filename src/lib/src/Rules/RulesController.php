<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\Builder;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\{
	AttemptToAccessNonExistingRuleException,
	NoConditionActionDefinedException,
	NoResponseActionDefinedException,
	NoSuchConditionHandlerException,
	NoSuchResponseHandlerException
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
	public $processComplete;

	public function __construct() {
		$this->processComplete = false;
	}

	protected function run() {

		// Rebuild the rules upon upgrade or settings change
		if ( true ) {
			$this->buildAndStore();
		}

		// Rebuild the rules when configuration is updated
		add_action( self::con()->prefix( 'after_pre_options_store' ), function ( $cfgChanged ) {
			if ( $cfgChanged ) {
				\method_exists( $this, 'buildAndStore' ) ? $this->buildAndStore() : ( new RulesStorageHandler() )->buildAndStore();
			}
		} );

		// Rebuild the rules every hour
		$this->setupCronHooks();
	}

	public function runHourlyCron() {
		$this->buildAndStore();
	}

	public function buildAndStore() {
		( new RulesStorageHandler() )->store( ( new Builder() )->run() );
	}

	public function isRulesEngineReady() :bool {
		return !empty( $this->getRules() );
	}

	/**
	 * @throws \Exception
	 */
	public function processRules() :void {
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

	/**
	 * @throws \Exception
	 */
	private function processRule( RuleVO $rule ) {
		if ( !isset( $rule->result ) ) {

			$conditionsResult = true;

			$resultMetaData = [];
			$conditions = $rule->conditions ?? null;
			if ( $conditions !== null ) {
				$processor = new Processors\ProcessConditions( $conditions );
				$conditionsResult = $processor->process();
				$resultMetaData = $processor->getConsolidatedMeta();
			}
			else {
				error_log( 'invalid: empty conditions for: '.var_export( $rule, true ) );
			}

			$rule->result = $conditionsResult;
			if ( $rule->result ) {
				( new Processors\ResponseProcessor( $rule, $resultMetaData ) )->run();
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
					( new RulesStorageHandler() )->loadRules()[ 'rules' ]
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
		if ( empty( $condition[ 'conditions' ] ) ) {
			throw new NoConditionActionDefinedException( 'No Condition Handler available for: '.var_export( $condition, true ) );
		}
		$class = $this->locateConditionHandlerClass( $condition[ 'conditions' ] );
		return new $class( $condition[ 'params' ] ?? [] );
	}

	/**
	 * @deprecated 18.5.8
	 */
	public function getDefaultEventFireResponseHandler() :Responses\EventFire {
		return new EventFire( [] );
	}

	/**
	 * @throws NoSuchConditionHandlerException
	 */
	public function locateConditionHandlerClass( string $conditionClassOrSlug ) :string {
		if ( \class_exists( $conditionClassOrSlug ) ) {
			$theHandlerClass = $conditionClassOrSlug;
		}
		else {
			$theHandlerClass = sprintf( '%s\\Conditions\\%s', __NAMESPACE__,
				\implode( '', \array_map( '\ucfirst', \explode( '_', $conditionClassOrSlug ) ) ) );
			if ( !\class_exists( $theHandlerClass ) ) {
				throw new NoSuchConditionHandlerException( 'No such Condition Handler Class for: '.$theHandlerClass );
			}
		}
		return $theHandlerClass;
	}

	/**
	 * @return Responses\Base|mixed
	 * @throws NoResponseActionDefinedException
	 * @throws NoSuchResponseHandlerException
	 */
	public function getResponseHandler( array $response ) {
		$responseClass = $response[ 'response' ] ?? null;
		if ( empty( $responseClass ) ) {
			throw new NoResponseActionDefinedException( 'No Response Handler available for: '.var_export( $response, true ) );
		}
		if ( !\class_exists( $response[ 'response' ] ) ) {
			throw new NoSuchResponseHandlerException( 'No Response Handler Class for: '.$responseClass );
		}
		return new $responseClass( $response[ 'params' ] ?? [] );
	}
}