<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\MutuallyDependentRulesException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\RulesControllerConsumer;

class Builder {

	use RulesControllerConsumer;

	public function run() :array {
		$rules = [];
		foreach ( $this->getRuleBuilders() as $builder ) {
			$rule = $builder->build();
			$rules[ $rule->slug ] = $rule;
		}

		$sorter = ( new SortRulesByDependencies( $rules ) )->setCon( $this->getRulesCon()->getCon() );
		try {
			$rules = $sorter->run();
		}
		catch ( MutuallyDependentRulesException $e ) {
			error_log( $e->getMessage() );
		}
		return $rules;
	}

	/**
	 * @return BuildRuleBase[]
	 */
	private function getRuleBuilders() :array {
		return \apply_filters( 'shield/collate_rule_builders', [] );
	}
}