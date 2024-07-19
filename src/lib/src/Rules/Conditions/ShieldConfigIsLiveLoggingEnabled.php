<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class ShieldConfigIsLiveLoggingEnabled extends Base {

	use Traits\TypeShield;

	public function getDescription() :string {
		return __( 'Is Shield live logging enabled.', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		return self::con()->comps->opts_lookup->getTrafficLiveLogTimeRemaining() > 0;
	}
}