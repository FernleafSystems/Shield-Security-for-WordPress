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

	protected function tooltip() :string {
		return __( 'Switch on/off ability of admins to manage other admin accounts', 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$status = parent::status();

		if ( self::con()->opts->optIs( 'admin_access_restrict_admin_users', 'Y' ) ) {
			if ( self::con()->comps->sec_admin->isEnabledSecAdmin() ) {
				$status[ 'level' ] = EnumEnabledStatus::GOOD;
			}
			else {
				$status[ 'level' ] = EnumEnabledStatus::OKAY;
				$status[ 'exp' ][] = __( "A PIN needs to be set to enable the Security Admin.", 'wp-simple-firewall' );
			}
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( "Turn on the option to restrict access to WordPress admin accounts.", 'wp-simple-firewall' );
		}

		return $status;
	}
}