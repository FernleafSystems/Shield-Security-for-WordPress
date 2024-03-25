<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class ShieldPro extends Base {

	public const SLUG = 'shieldpro';
	public const WEIGHT = 5;

	protected function isApplicable() :bool {
		return !self::con()->comps->whitelabel->isEnabled();
	}

	protected function testIfProtected() :bool {
		return self::con()->isPremiumActive();
	}

	public function hrefFull() :string {
		return self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_LICENSE );
	}

	public function title() :string {
		return __( 'ShieldPRO Premium Security', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Your site benefits from additional security protection provided by ShieldPRO.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Your site doesn't benefit from the additional security protection provided by ShieldPRO.", 'wp-simple-firewall' );
	}
}