<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;

class WpUpdates extends Base {

	public const SLUG = 'wp_updates';
	public const WEIGHT = 5;

	protected function testIfProtected() :bool {
		return !Services::WpGeneral()->hasCoreUpdate();
	}

	protected function hrefFull() :string {
		return Services::WpGeneral()->getAdminUrl_Updates();
	}

	protected function hrefFullTargetBlank() :bool {
		return true;
	}

	public function title() :string {
		return __( 'WordPress Version', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( "WordPress has all available upgrades applied.", 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "There is an upgrade available for WordPress.", 'wp-simple-firewall' );
	}
}