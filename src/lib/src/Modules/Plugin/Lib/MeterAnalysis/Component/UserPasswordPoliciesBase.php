<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Options;

abstract class UserPasswordPoliciesBase extends Base {

	use Traits\OptConfigBased;

	protected function getOptConfigKey() :string {
		return 'enable_password_policies';
	}

	protected function testIfProtected() :bool {
		$mod = self::con()->getModule_UserManagement();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled() && $opts->isPasswordPoliciesEnabled();
	}
}