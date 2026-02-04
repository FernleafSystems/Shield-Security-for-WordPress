<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class IsUserSecurityAdmin extends Base {

	use Traits\TypeShield;

	public const SLUG = 'is_user_security_admin';

	public function getDescription() :string {
		return __( "Is current user Security Admin.", 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		$secAdminCon = self::con()->comps->sec_admin;
		return (
			!self::con()->comps->opts_lookup->isPluginEnabled()
			|| !$secAdminCon->isEnabledSecAdmin()
			|| $secAdminCon->isRegisteredSecAdminUser( Services::WpUsers()->getCurrentWpUser() )
			|| $secAdminCon->getSecAdminTimeRemaining() > 0
		);
	}
}