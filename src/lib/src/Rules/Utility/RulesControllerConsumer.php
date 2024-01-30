<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\RulesController;

/**
 * @deprecated 18.5.8
 */
trait RulesControllerConsumer {

	/**
	 * @var RulesController
	 */
	protected $rulesCon;

	public function getRulesCon() :RulesController {
		return $this->rulesCon;
	}

	/**
	 * @deprecated 18.5.8
	 */
	public function setRulesCon( RulesController $rulesCon ) {
		$this->rulesCon = $rulesCon;
		return $this;
	}
}