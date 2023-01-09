<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

class SecurityAdmin extends Base {

	public const SLUG = 'security_admin';
	public const WEIGHT = 40;

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_SecAdmin();
		return $mod->isModOptEnabled() && $mod->getSecurityAdminController()->isEnabledSecAdmin();
	}

	public function href() :string {
		$mod = $this->getCon()->getModule_SecAdmin();
		return $mod->isModOptEnabled() ? $this->link( 'admin_access_key' ) : $this->link( 'enable_admin_access_restriction' );
	}

	public function title() :string {
		return __( 'Security Admin Protection', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'The security plugin is protected against tampering through use of a Security Admin PIN.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "The security plugin isn't protected against tampering through use of a Security Admin PIN.", 'wp-simple-firewall' );
	}
}