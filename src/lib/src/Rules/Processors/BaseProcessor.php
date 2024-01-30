<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\RulesController;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\RuleVO;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\RulesControllerConsumer;

/**
 * @deprecated 18.5.8
 */
class BaseProcessor {

	use RulesControllerConsumer;

	/**
	 * @var RuleVO
	 */
	protected $rule;

	public function __construct( RuleVO $rule, RulesController $rulesCon = null ) {
		$this->setRulesCon( $rulesCon );
		$this->rule = $rule;
	}
}