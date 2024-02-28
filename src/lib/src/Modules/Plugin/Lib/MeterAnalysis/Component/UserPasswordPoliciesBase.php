<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

abstract class UserPasswordPoliciesBase extends Base {

	use Traits\OptConfigBased;

	protected function getOptConfigKey() :string {
		return 'enable_password_policies';
	}

	protected function testIfProtected() :bool {
		return self::con()->comps->opts_lookup->isPassPoliciesEnabled();
	}
}