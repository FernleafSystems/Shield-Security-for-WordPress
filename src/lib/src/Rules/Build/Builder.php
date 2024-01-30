<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\RuleVO;

class Builder {

	public function run() :array {
		$rules = [];
		foreach ( ( new RuleBuilderEnumerator() )->run() as $builder ) {
			$rule = $builder->build();
			$rules[ $rule->slug ] = $rule;
		}

		( new AssignMinimumHooks( $rules ) )->run();

		return \array_filter( $rules, function ( RuleVO $rule ) {
			return $rule->is_valid;
		} );
	}
}