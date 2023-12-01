<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\RuleVO;

abstract class Base {

	use PluginControllerConsumer;

	public const SLUG = '';

	/**
	 * @var array
	 */
	protected $responseParams;

	/**
	 * @var array
	 */
	protected $conditionTriggerMeta;

	public function __construct( array $responseParams = [], array $conditionTriggerMeta = [] ) {
		$this->responseParams = $responseParams;
		$this->conditionTriggerMeta = $conditionTriggerMeta;
	}

	/**
	 * @deprecated 18.5.8
	 */
	public function setRule( RuleVO $rule ) :self {
		return $this;
	}

	/**
	 * @deprecated 18.5.8
	 */
	public function setConditionTriggerMeta( array $meta ) :self {
		$this->conditionTriggerMeta = $meta;
		return $this;
	}

	/**
	 * @throws \Exception
	 */
	abstract public function execResponse() :bool;

	protected function getConsolidatedConditionMeta() :array {
		$meta = [];
		foreach ( $this->conditionTriggerMeta as $m ) {
			$meta = \array_merge( $meta, $m );
		}
		return $meta;
	}
}