<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	RuleVO,
	WPHooksOrder
};

abstract class BuildRuleBase {

	public const LOGIC_AND = 'AND';
	public const LOGIC_OR = 'OR';

	public function build() :RuleVO {
		$rule = new RuleVO();
		$rule->class = \get_class( $this );
		$rule->slug = $this->getSlug();
		$rule->name = $this->getName();
		$rule->description = $this->getDescription();
		$rule->wp_hook = '';
		$rule->wp_hook_level = $this->getWpHookLevel();
		$rule->wp_hook_priority = $this->getWpHookPriority();
		$rule->flags = $this->getFlags();
		$rule->conditions = $this->getConditions();
		$rule->responses = $this->getResponses();
		$rule->immediate_exec_response = $this->isInstantExecResponse();
		return $rule;
	}

	abstract protected function getSlug() :string;

	abstract protected function getName() :string;

	abstract protected function getDescription() :string;

	protected function getWpHook() :string {
		return ''; // you need a VERY good reason to overload this method as hooks are handled dynamically
	}

	protected function getWpHookLevel() :int {
		return WPHooksOrder::NONE;
	}

	protected function getWpHookPriority() :?int {
		return null;
	}

	abstract protected function getConditions() :array;

	protected function getFlags() :array {
		return [];
	}

	protected function getResponses() :array {
		return [];
	}

	protected function isInstantExecResponse() :bool {
		return false;
	}
}