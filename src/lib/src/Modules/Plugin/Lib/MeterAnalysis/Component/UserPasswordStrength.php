<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Options;

class UserPasswordStrength extends UserPasswordPoliciesBase {

	public const SLUG = 'user_pass_strength';

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_UserManagement();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return parent::isProtected() && $opts->getPassMinStrength() >= 3;
	}

	public function href() :string {
		$mod = $this->getCon()->getModule_UserManagement();
		return $mod->isModOptEnabled() ? $this->link( 'pass_min_strength' ) : $this->link( 'enable_user_management' );
	}

	public function title() :string {
		return __( 'Strong Passwords', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'All new passwords are required to be be of high strength.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "There is no requirement for strong user passwords.", 'wp-simple-firewall' );
	}
}