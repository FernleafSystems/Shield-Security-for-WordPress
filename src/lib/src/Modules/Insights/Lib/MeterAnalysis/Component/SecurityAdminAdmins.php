<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Options;

class SecurityAdminAdmins extends Base {

	public const SLUG = 'security_admin_admins';
	public const WEIGHT = 20;

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_SecAdmin();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled()
			   && $mod->getSecurityAdminController()->isEnabledSecAdmin()
			   && $opts->isSecAdminRestrictUsersEnabled();
	}

	public function href() :string {
		$mod = $this->getCon()->getModule_SecAdmin();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		if ( !$mod->isModOptEnabled() ) {
			$href = $this->link( 'enable_admin_access_restriction' );
		}
		elseif ( !$opts->hasSecurityPIN() ) {
			$href = $this->link( 'admin_access_key' );
		}
		elseif ( !$opts->hasSecurityPIN() ) {
			$href = $this->link( 'admin_access_timeout' );
		}
		else {
			$href = $this->link( 'admin_access_restrict_admin_users' );
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