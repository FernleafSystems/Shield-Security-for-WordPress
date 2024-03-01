<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Options;

class SecurityAdminAdmins extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'security_admin_admins';
	public const WEIGHT = 3;

	protected function getOptConfigKey() :string {
		return 'admin_access_restrict_admin_users';
	}

	protected function testIfProtected() :bool {
		return self::con()->comps->sec_admin->isEnabledSecAdmin()
			   && self::con()->opts->optIs( 'admin_access_restrict_admin_users', 'Y' );
	}

	public function href() :string {
		$lookup = self::con()->comps->opts_lookup;
		if ( !$lookup->isModFromOptEnabled( 'admin_access_key' ) ) {
			$href = $this->getOptLink( 'enable_admin_access_restriction' );
		}
		elseif ( empty( $lookup->getSecAdminPIN() ) ) {
			$href = $this->getOptLink( 'admin_access_key' );
		}
		else {
			$href = $this->getOptLink( 'admin_access_restrict_admin_users' );
		}
		return $href;
	}

	public function title() :string {
		return __( 'WordPress Admins Protection', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'WordPress admin accounts are protected against tampering from other WordPress admins.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "WordPress admin accounts aren't protected against tampering from other WordPress admins.", 'wp-simple-firewall' );
	}
}