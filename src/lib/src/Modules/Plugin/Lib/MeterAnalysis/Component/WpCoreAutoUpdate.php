<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;

class WpCoreAutoUpdate extends Base {

	public const SLUG = 'wp_core_autoupdate';
	public const WEIGHT = 50;

	public function href() :string {
		return $this->link( $this->getCon()->getModule_Autoupdates()->getSlug() );
	}

	protected function isProtected() :bool {
		return Services::WpGeneral()->canCoreUpdateAutomatically();
	}

	public function title() :string {
		return __( 'WordPress Core Automatic Updates', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( "WordPress Core is automatically updated when minor upgrades are released.", 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "WordPress Core isn't automatically updated when minor upgrades are released.", 'wp-simple-firewall' );
	}
}