<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Options;

class SecurityAdminAdmins extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'security_admin_admins';
	public const WEIGHT = 3;

	protected function getOptConfigKey() :string {
		return 'enable_admin_access_restriction';
	}

	protected function testIfProtected() :bool {
		$mod = $this->con()->getModule_SecAdmin();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled()
			   && $mod->getSecurityAdminController()->isEnabledSecAdmin()
			   && $opts->isSecAdminRestrictUsersEnabled();
	}

	public function href() :string {
		$mod = $this->con()->getModule_SecAdmin();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		if ( !$mod->isModOptEnabled() ) {
			$href = $this->getOptLink( 'enable_admin_access_restriction' );
		}
		elseif ( !$opts->hasSecurityPIN() ) {
			$href = $this->getOptLink( 'admin_access_key' );
		}
		elseif ( !$opts->hasSecurityPIN() ) {
			$href = $this->getOptLink( 'admin_access_timeout' );
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