<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;

class ShieldPro extends Base {

	public const SLUG = 'shieldpro';
	public const WEIGHT = 35;

	protected function isProtected() :bool {
		return $this->getCon()->isPremiumActive();
	}

	public function href() :string {
		return $this->getCon()->plugin_urls->adminTop( PluginURLs::NAV_LICENSE );
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