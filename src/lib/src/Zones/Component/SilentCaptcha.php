<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class SilentCaptcha extends Base {

	public function title() :string {
		return __( 'silentCAPTCHA', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'silentCAPTCHA configuration to help detect and block bad bots.', 'wp-simple-firewall' );
	}

	public function enabledStatus() :string {
		return EnumEnabledStatus::GOOD;
	}
}