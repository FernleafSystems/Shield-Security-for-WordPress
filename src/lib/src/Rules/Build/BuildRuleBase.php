<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\RuleVO;

abstract class BuildRuleBase {

	use Shield\Modules\ModConsumer;

	const LOGIC_AND = 'AND';
	const LOGIC_OR = 'OR';

	public function build() :Shield\Rules\RuleVO {
		$rule = new RuleVO();
		$rule->name = $this->getName();
		$rule->description = $this->getDescription();
		$rule->slug = $this->getSlug();
		$rule->priority = $this->getPriority();
		$rule->flags = $this->getFlags();
		$rule->conditions = $this->getConditions();
		$rule->responses = $this->getResponses();
		return $rule;
	}

	abstract protected function getName() :string;

	abstract protected function getDescription() :string;

	protected function getConditions() :array {
		return [];
	}

	protected function getFlags() :array {
		return [];
	}

	protected function getPriority() :int {
		return 100;
	}

	protected function getResponses() :array {
		return [];
	}
}