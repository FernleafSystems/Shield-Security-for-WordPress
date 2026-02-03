<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class ShieldConfigIsLiveLoggingEnabled extends Base {

	use Traits\TypeShield;

	public function getDescription() :string {
		return sprintf( __( 'Is %s live logging enabled.', 'wp-simple-firewall' ), self::con()->labels->Name );
	}

	protected function execConditionCheck() :bool {
		return self::con()->comps->opts_lookup->getTrafficLiveLogTimeRemaining() > 0;
	}
}