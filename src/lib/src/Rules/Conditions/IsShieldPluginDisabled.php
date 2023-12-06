<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class IsShieldPluginDisabled extends Base {

	public const SLUG = 'is_shield_plugin_disabled';

	public function getDescription() :string {
		return __( 'Is Shield Plugin Functionality Disabled.', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options $opts */
		$opts = self::con()->getModule_Plugin()->opts();
		return $opts->isPluginGloballyDisabled();
	}
}