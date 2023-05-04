<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Options;

class UserPasswordPwned extends UserPasswordPoliciesBase {

	public const SLUG = 'user_pass_pwned';

	protected function getOptConfigKey() :string {
		return 'pass_prevent_pwned';
	}

	protected function testIfProtected() :bool {
		$mod = $this->con()->getModule_UserManagement();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return parent::testIfProtected() && $opts->isPassPreventPwned();
	}

	public function title() :string {
		return __( 'Pwned Passwords', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Pwned passwords are blocked from being set by any user.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Pwned passwords are permitted.", 'wp-simple-firewall' );
	}
}