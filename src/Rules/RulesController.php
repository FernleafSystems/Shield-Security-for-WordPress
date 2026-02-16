<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Request\ThisRequestConsumer;

class RulesController {

	use ExecOnce;
	use PluginCronsConsumer;
	use PluginControllerConsumer;
	use ThisRequestConsumer;

	/**
	 * @var RuleVO[]
	 */
	private ?array $rules = null;

	public bool $processComplete;

	private ConditionMetaStore $conditionMeta;

	public function __construct() {
		$this->processComplete = false;
	}

	protected function run() {

		// Rebuild the rules upon upgrade or settings change
		if ( self::con()->cfg->rebuilt ) {
			$this->buildAndStore();
		}

		// Rebuild the rules when configuration is updated
		add_action(
			self::con()->prefix( 'after_pre_options_store' ),
			fn( $cfgChanged ) => $cfgChanged && $this->buildAndStore()
		);

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
		}
	}

	/**
	 * @throws \Exception
	 */
	private function processRule( RuleVO $rule ) {
		if ( !isset( $rule->result ) ) {
			$conditions = $rule->conditions ?? null;
			if ( $conditions !== null ) {
				$processor = new Processors\ProcessConditions( $conditions );
				$rule->result = $processor->setThisRequest( $this->req )->process();
			}
			else {
				$rule->result = true;
				error_log( 'invalid: empty conditions for: '.var_export( $rule, true ) );
			}

			if ( $rule->result ) {
				( new Processors\ResponseProcessor( $rule ) )
					->setThisRequest( $this->req )
					->run();
			}
		}
	}

	/**
	 * @return RuleVO[]
	 */
	public function getRules() :array {
		try {
			$this->rules ??= \array_map(
				fn( $rule ) => ( new RuleVO() )->applyFromArray( $rule ),
				( new RulesStorageHandler() )->loadRules()[ 'rules' ]
			);
		}
		catch ( \Exception $e ) {
			$this->rules = [];
		}
		return $this->rules;
	}

	public function getConditionMeta() :ConditionMetaStore {
		return $this->conditionMeta ??= new ConditionMetaStore();
	}
}