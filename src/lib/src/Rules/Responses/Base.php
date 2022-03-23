<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\RuleVO;

abstract class Base {

	use PluginControllerConsumer;

	const SLUG = '';

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

	public function run() :bool {
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