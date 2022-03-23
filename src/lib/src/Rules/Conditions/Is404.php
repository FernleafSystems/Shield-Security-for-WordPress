<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\WPHooksOrder;

class Is404 extends Base {

	const SLUG = 'is_404';

	protected function execConditionCheck() :bool {
		$statusMatcher = ( new MatchRequestStatus() )->setCon( $this->getCon() );
		$statusMatcher->status = '404';
		if ( $match = $statusMatcher->run() ) {
			$this->conditionTriggerMeta = $statusMatcher->getConditionTriggerMetaData();
		}
		return $match;
	}

	public static function MinimumHook() :int {
		return WPHooksOrder::TEMPLATE_REDIRECT;
	}

	public static function RequiredConditions() :array {
		return [
			MatchRequestStatus::class
		];
	}
}