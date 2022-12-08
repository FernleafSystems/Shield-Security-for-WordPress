<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\RuleVO;

abstract class Base {

	use PluginControllerConsumer;

	public const SLUG = '';

	/**
	 * @var RuleVO
	 */
	protected $rule;

	/**
	 * @var array
	 */
	protected $responseParams;

	/**
	 * @var array
	 */
	protected $conditionTriggerMeta;

	public function __construct( array $responseParams ) {
		$this->responseParams = $responseParams;
	}

	public function setRule( RuleVO $rule ) {
		$this->rule = $rule;
		return $this;
	}

	public function setConditionTriggerMeta( array $meta ) :self {
		$this->conditionTriggerMeta = $meta;
		return $this;
	}

	public function run() {
		$con = $this->getCon();
		if ( $this->rule->immediate_exec_response || did_action( $con->prefix( 'after_run_processors' ) ) ) {
			$this->runExecResponse();
		}
		else {
			add_action( $con->prefix( 'after_run_processors' ), function () {
				$this->runExecResponse();
			} );
		}
	}

	private function runExecResponse() :bool {
		try {
			return $this->execResponse();
		}
		catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * @throws \Exception
	 */
	abstract protected function execResponse() :bool;

	protected function getConsolidatedConditionMeta() :array {
		$meta = [];
		foreach ( $this->conditionTriggerMeta as $m ) {
			$meta = array_merge( $meta, $m );
		}
		return $meta;
	}
}