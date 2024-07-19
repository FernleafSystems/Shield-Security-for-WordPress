<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class PasswordPolicies extends Base {

	public function title() :string {
		return __( 'Password Policies', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Restrict password parameters to ensure higher user account security.', 'wp-simple-firewall' );
	}
}