<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build;

class RuleBuilderEnumerator {

	/**
	 * @return BuildRuleBase[]
	 */
	public function run() :array {
		return $this->viaFilters();
	}

	/**
	 * @return BuildRuleBase[]
	 */
	private function viaFilters() :array {
		return \apply_filters( 'shield/collate_rule_builders', [] );
	}
}