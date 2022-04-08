<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\RulesController;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\RuleVO;

class BaseProcessor {

	use PluginControllerConsumer;

	/**
	 * @var RuleVO
	 */
	protected $rule;

	/**
	 * @var RulesController
	 */
	protected $controller;

	public function __construct( RuleVO $rule, RulesController $controller ) {
		$this->rule = $rule;
		$this->controller = $controller;
	}
}