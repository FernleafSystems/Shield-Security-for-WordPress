<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\RulesController;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\RuleVO;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\RulesControllerConsumer;

class BaseProcessor {

	use RulesControllerConsumer;
	use PluginControllerConsumer;

	/**
	 * @var RuleVO
	 */
	protected $rule;

	public function __construct( RuleVO $rule, RulesController $rulesCon ) {
		$this->setRulesCon( $rulesCon );
		$this->rule = $rule;
	}
}