<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\RulesController;

class Builder {

	use PluginControllerConsumer;

	public function run( RulesController $controller ) {
		$rawRules = [];
		foreach ( $this->getRules() as $builder ) {
			$rule = $builder->build();
			$rawRules[ $rule->slug ] = $rule->getRawData();
		}
		$controller->storeRules( $rawRules );
	}

	/**
	 * @return BuildRuleBase[]
	 */
	private function getRules() :array {
		return \apply_filters( 'shield/collate_rule_builders', [] );
	}
}