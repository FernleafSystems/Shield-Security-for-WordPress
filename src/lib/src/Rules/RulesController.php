<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\Rules\Ops as RulesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Request\ThisRequestConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\{
	AttemptToAccessNonExistingRuleException,
	NoConditionActionDefinedException,
	NoResponseActionDefinedException,
	NoSuchConditionHandlerException,
	NoSuchResponseHandlerException
};

class RulesController {

	use ExecOnce;
	use PluginCronsConsumer;
	use PluginControllerConsumer;
	use ThisRequestConsumer;

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
		( new RulesStorageHandler() )->store( ( new Build\Builder() )->run() );
	}

	public function isRulesEngineReady() :bool {
		return !empty( $this->getRules() );
	}

	/**
	 * @throws \Exception
	 */
	public function processRules() :void {
		if ( !$this->processComplete ) {

			$this->processComplete = true;

			foreach ( $this->getRules() as $rule ) {
				$hook = $rule->wp_hook;
				if ( empty( $hook ) ) {
					$this->processRule( $rule );
				}
				else {
					add_action( $hook, function () use ( $rule ) {
						$this->processRule( $rule );
					}, $rule->wp_hook_priority );
				}
			}

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
			$resultMetaData = [];
			$conditions = $rule->conditions ?? null;
			if ( $conditions !== null ) {
				$processor = new Processors\ProcessConditions( $conditions );
				$rule->result = $processor->setThisRequest( $this->req )->process();
				$resultMetaData = $processor->getConsolidatedMeta();
			}
			else {
				$rule->result = true;
				error_log( 'invalid: empty conditions for: '.var_export( $rule, true ) );
			}

			if ( $rule->result ) {
				( new Processors\ResponseProcessor( $rule, $resultMetaData ) )
					->setThisRequest( $this->req )
					->run();
			}
		}
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
	 * @throws AttemptToAccessNonExistingRuleException
	 * @deprecated 18.6
	 */
	public function getRule( string $slug ) :RuleVO {
		throw new AttemptToAccessNonExistingRuleException();
	}

	/**
	 * @return RuleVO[]
	 * @deprecated 18.6
	 */
	private function getImmediateRules() :array {
		return [];
	}

	/**
	 * @return RuleVO[]
	 * @deprecated 18.6
	 */
	private function getRulesForHook() :array {
		return [];
	}

	/**
	 * @deprecated 18.6
	 */
	public function getDefaultEventFireResponseHandler() :Responses\EventFire {
		return new Responses\EventFire( [] );
	}

	/**
	 * @throws NoSuchConditionHandlerException
	 * @deprecated 18.6
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
	 * @deprecated 18.6
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

	/**
	 * @return Conditions\Base|mixed
	 * @throws NoConditionActionDefinedException
	 * @deprecated 18.6
	 */
	public function getConditionHandler( array $condition ) {
		if ( empty( $condition[ 'conditions' ] ) ) {
			throw new NoConditionActionDefinedException( 'No Condition Handler available for: '.var_export( $condition, true ) );
		}
		$class = Utility\FindFromSlug::Condition( $condition[ 'conditions' ] );
		return new $class( $condition[ 'params' ] ?? [] );
	}
}