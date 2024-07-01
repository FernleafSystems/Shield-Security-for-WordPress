<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class SecadminWpAdmins extends Base {

	public function title() :string {
		return __( 'Administrator Accounts Protection', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Prevent creation, deletion, or demotion of WordPress administrator accounts.', 'wp-simple-firewall' );
	}

	public function enabledStatus() :string {
		return self::con()->opts->optIs( 'admin_access_restrict_admin_users', 'Y' ) ? EnumEnabledStatus::GOOD : EnumEnabledStatus::BAD;
	}
}