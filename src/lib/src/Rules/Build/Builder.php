<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\RulesController;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\RuleVO;

class Builder {

	use PluginControllerConsumer;

	public function run( RulesController $controller ) {
		$rules = [];
		foreach ( $this->getRules() as $builder ) {
			$rule = $builder->build();
			$rules[ $rule->slug ] = $rule;
		}

		usort( $rules,
			function ( $a, $b ) {
				/**
				 * @var RuleVO $a
				 * @var RuleVO $b
				 */
				if ( $a->priority == $b->priority ) {
					return 0;
				}
				return ( $a->priority < $b->priority ) ? -1 : 1;
			}
		);

		$controller->storeRules( $rules );
	}

	/**
	 * @return BuildRuleBase[]
	 */
	private function getRules() :array {
		return \apply_filters( 'shield/collate_rule_builders', [] );
	}
}