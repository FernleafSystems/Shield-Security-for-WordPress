<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class Base {

	use PluginControllerConsumer;

	const SLUG = '';

	protected $conditionTriggerMeta;

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
}