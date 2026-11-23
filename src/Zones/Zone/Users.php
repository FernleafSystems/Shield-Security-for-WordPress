<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class Users extends Base {

	public function components() :array {
		return [
			Component\PasswordPolicies::class,
			Component\PwnedPasswords::class,
			Component\PasswordStrength::class,
			Component\InactiveUsers::class,
			Component\SpamUserRegisterBlock::class,
		];
	}

	public function title() :string {
		return __( 'Users', 'wp-simple-firewall' );
	}
}
