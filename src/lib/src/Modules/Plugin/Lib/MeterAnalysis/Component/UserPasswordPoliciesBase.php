<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Options;

abstract class UserPasswordPoliciesBase extends Base {

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_UserManagement();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled() && $opts->isPasswordPoliciesEnabled();
	}

	public function href() :string {
		$mod = $this->getCon()->getModule_UserManagement();
		return $mod->isModOptEnabled() ? $this->link( 'enable_password_policies' ) : $this->link( 'enable_user_management' );
	}
}