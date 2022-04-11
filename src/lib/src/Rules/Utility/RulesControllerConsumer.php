<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\RulesController;

trait RulesControllerConsumer {

	/**
	 * @var RulesController
	 */
	protected $rulesCon;

	public function setRulesCon( RulesController $rulesCon ) {
		$this->rulesCon = $rulesCon;
		return $this;
	}
}