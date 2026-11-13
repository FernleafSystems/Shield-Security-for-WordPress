<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class Login extends Base {

	public function components() :array {
		return [
			Component\LoginProtectionForms::class,
			Component\LoginHide::class,
			Component\TwoFactorAuth::class,
			Component\SessionTheftProtection::class,
		];
	}

	public function title() :string {
		return __( 'Login', 'wp-simple-firewall' );
	}
}
