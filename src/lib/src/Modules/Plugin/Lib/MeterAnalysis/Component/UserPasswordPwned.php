<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Options;

class UserPasswordPwned extends UserPasswordPoliciesBase {

	public const SLUG = 'user_pass_pwned';

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_UserManagement();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return parent::isProtected() && $opts->isPassPreventPwned();
	}

	public function href() :string {
		$mod = $this->getCon()->getModule_UserManagement();
		return $mod->isModOptEnabled() ? $this->link( 'pass_prevent_pwned' ) : $this->link( 'enable_user_management' );
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