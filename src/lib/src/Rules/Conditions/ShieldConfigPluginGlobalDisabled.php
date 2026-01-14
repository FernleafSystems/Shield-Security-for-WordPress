<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class ShieldConfigPluginGlobalDisabled extends Base {

	use Traits\TypeShield;

	public const SLUG = 'is_shield_plugin_disabled';

	public function getDescription() :string {
		return sprintf( __( 'Is %s Plugin Functionality Disabled.', 'wp-simple-firewall' ), self::con()->labels->Name );
	}

	protected function execConditionCheck() :bool {
		return !self::con()->comps->opts_lookup->isPluginEnabled();
	}
}