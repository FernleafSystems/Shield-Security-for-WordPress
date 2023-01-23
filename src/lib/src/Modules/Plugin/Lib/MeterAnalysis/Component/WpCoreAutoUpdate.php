<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;

class WpCoreAutoUpdate extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'wp_core_autoupdate';
	public const WEIGHT = 6;

	protected function getOptConfigKey() :string {
		return 'autoupdate_core';
	}

	protected function testIfProtected() :bool {
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