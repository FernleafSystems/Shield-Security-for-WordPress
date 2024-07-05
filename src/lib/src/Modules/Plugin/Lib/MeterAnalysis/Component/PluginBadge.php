<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class PluginBadge extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'plugin_badge';
	public const WEIGHT = 1;

	protected function getOptConfigKey() :string {
		return 'display_plugin_badge';
	}

	protected function testIfProtected() :bool {
		return self::con()->opts->optIs( 'display_plugin_badge', 'Y' );
	}

	public function title() :string {
		return __( 'Plugin Security Badge', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Your customers and visitors are reassured that you take their security seriously.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Your customers and visitors aren't given reassurance that you take their security seriously.", 'wp-simple-firewall' );
	}
}