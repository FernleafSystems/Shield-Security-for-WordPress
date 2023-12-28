<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	RuleVO,
	Utility\ExtractSubConditions,
	WPHooksOrder
};

class SortRulesByDependencies {

	use PluginControllerConsumer;

	/**
	 * @var RuleVO[]
	 */
	private $rules;

	private $dependencies = [];

	private $finalRulesOrder = [];

	public function __construct( array $rules ) {
		$this->rules = $rules;
	}

	/**
	 * @return RuleVO[]
	 */
	public function run() :array {
		$this->assignWpHooks();
//		$this->verifyDependencies();
//		$this->orderRules();
		return $this->rules;
	}

	/**
	 * Assigns the most appropriate WP Hook to a rule based on its (sub)conditions.
	 * @throws \Exception
	 */
	private function assignWpHooks() {
		foreach ( $this->rules as $rule ) {
			$minimumHook = WPHooksOrder::NONE;
			/** @var Conditions\Base[] $allConditions */
			$allConditions = ( new ExtractSubConditions() )->fromRule( $rule )[ 'classes' ];
			foreach ( $allConditions as $condition ) {
				$minimumHook = \max( $minimumHook, $condition::MinimumHook() );
			}
			$rule->wp_hook_level = \max( $minimumHook, $rule->wp_hook_level );
			$rule->wp_hook = WPHooksOrder::HOOK_NAME( $rule->wp_hook_level );
		}
	}
}