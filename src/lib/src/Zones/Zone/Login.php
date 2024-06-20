<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class Login extends Base {

	public function components() :array {
		return [
			Component\LimitLogin::class,
			Component\SessionTheftProtection::class,
			Component\TwoFactorAuth::class,
		];
	}

	public function description() :array {
		return [
			__( 'Protection for user login and sessions.', 'wp-simple-firewall' ),
		];
	}

	public function icon() :string {
		return 'person-badge-fill';
	}

	public function title() :string {
		return __( 'Login', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Protection for user login and sessions.', 'wp-simple-firewall' );
	}
}