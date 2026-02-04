<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class Login extends Base {

	public function tooltip() :string {
		return 'Edit settings for the entire Login Security Zone';
	}

	public function components() :array {
		return [
			Component\LoginProtectionForms::class,
			Component\TwoFactorAuth::class,
			Component\SessionTheftProtection::class,
		];
	}

	public function description() :array {
		return [
			__( 'Protection for user logins is achieved through several key strategies.', 'wp-simple-firewall' ),
			__( 'Firstly, we must limit the login attempts of automated bots that brute force your login form.', 'wp-simple-firewall' ),
			__( 'We must then verify the identity of the user logging-in using two-factor authentication.', 'wp-simple-firewall' ),
			__( 'Finally, we must prevent malicious actors gaining administrative WordPress access to your site by stealing your legitimate user sessions.', 'wp-simple-firewall' ),
		];
	}

	public function icon() :string {
		return 'person-badge-fill';
	}

	public function title() :string {
		return __( 'Login', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Protection for user logins alongside session hijacking prevention.', 'wp-simple-firewall' );
	}

	protected function getUnderlyingModuleZone() :?string {
		return Component\Modules\ModuleLogin::class;
	}
}