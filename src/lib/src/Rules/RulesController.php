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
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors\{
	ConditionsProcessor,
	ResponseProcessor
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses\EventFire;
use FernleafSystems\Wordpress\Services\Services;

class RulesController {

	use ExecOnce;
	use PluginCronsConsumer;
	use PluginControllerConsumer;

	/**
	 * @var RuleVO[]
	 */
	private $rules;

	protected function canRun() :bool {
		return !$this->getCon()->this_req->rules_completed;
	}

	protected function run() {
		if ( $this->verifyRulesStatus() ) {
			$this->processRules();
			add_action( $this->getCon()->prefix( 'plugin_shutdown' ), function () {
//				error_log( var_export( $this->getRulesResultsSummary(), true ) );
			} );
		}
		add_action( $this->getCon()->prefix( 'pre_options_store' ), function () {
			$this->buildRules();
		} );
		$this->setupCronHooks();
	}

	public function runHourlyCron() {
		$this->buildRules();
	}

	public function getRulesResultsSummary() :array {
		return array_map(
			function ( $rule ) {
				return $rule->result;
			},
			$this->getRules()
		);
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
			}, PHP_INT_MIN );
		}

		$this->getCon()->this_req->rules_completed = true;
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
	protected function getRules() :array {
		if ( !isset( $this->rules ) ) {
			try {
				$this->rules = array_map(
					function ( $rule ) {
						return ( new RuleVO() )->applyFromArray( $rule );
					},
					$this->load()[ 'rules' ]
				);
			}
			catch ( \Exception $e ) {
				$this->rules = [];
			}
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

	/**
	 * @throws \Exception
	 */
	public function load( bool $attemptRebuild = true ) :array {
		$FS = Services::WpFs();
		$rules = Services::WpGeneral()->getOption( $this->getCon()->prefix( 'rules' ) );
		if ( empty( $rules ) || !is_array( $rules ) ) {

			if ( $FS->isFile( $this->getPathToRules() ) ) {
				$rules = json_decode( $FS->getFileContent( $this->getPathToRules() ), true );
				Services::WpGeneral()->updateOption( $this->getCon()->prefix( 'rules' ), $rules );
			}
			elseif ( $attemptRebuild ) {
				$this->buildRules();
				return $this->load( false );
			}
		}
		if ( !is_array( $rules ) || empty( $rules ) ) {
			throw new \Exception( 'No rules to load' );
		}
		return $rules;
	}

	public function store( array $rules ) :bool {
		$WP = Services::WpGeneral();
		$req = Services::Request();
		$data = [
			'ts'    => $req->ts(),
			'time'  => $WP->getTimeStampForDisplay( $req->ts() ),
			'rules' => array_map( function ( RuleVO $rule ) {
				return $rule->getRawData();
			}, $rules ),
		];
		$WP->updateOption( $this->getCon()->prefix( 'rules' ), $data );
		return Services::WpFs()->putFileContent( $this->getPathToRules(), wp_json_encode( $data ) );
	}

	private function verifyRulesStatus() :bool {
		return !empty( $this->getRules() );
	}
}