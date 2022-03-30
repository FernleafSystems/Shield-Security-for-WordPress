<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\MutuallyDependentRulesException;
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

		$sorter = ( new SortRulesByDependencies($rules) )->setCon( $this->getCon() );
		try {
			$rules = $sorter->run();
			$controller->store( $rules );
		}
		catch ( MutuallyDependentRulesException $e ) {
			error_log( $e->getMessage() );
		}

	}

	/**
	 * @return BuildRuleBase[]
	 */
	private function getRules() :array {
		return \apply_filters( 'shield/collate_rule_builders', [] );
	}
}