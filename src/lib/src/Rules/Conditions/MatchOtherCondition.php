<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

/**
 * @property string $other_condition_slug
 */
class MatchOtherCondition extends Base {

	const SLUG = 'match_other_condition';

	protected function execConditionCheck() :bool {
		$result = $this->getCon()
					  ->req
					  ->rules_conditions_results[ $this->other_condition_slug ] ?? null;
		if ( is_null( $result ) ) {
			throw new \Exception( 'Other condition has not run yet!' );
		}
		return $result;
	}

	protected function getPreviousResult() {
		return null;
	}
}