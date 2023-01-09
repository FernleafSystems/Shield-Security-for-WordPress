<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Options;

class SecurityAdminOptions extends Base {

	public const SLUG = 'security_admin_options';
	public const WEIGHT = 20;

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_SecAdmin();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled()
			   && $mod->getSecurityAdminController()->isEnabledSecAdmin()
			   && $opts->isRestrictWpOptions();
	}

	public function href() :string {
		$mod = $this->getCon()->getModule_SecAdmin();
		return $mod->isModOptEnabled() ? $this->link( 'admin_access_restrict_options' ) : $this->link( 'enable_admin_access_restriction' );
	}

	public function title() :string {
		return __( 'Critical WordPress Settings Protection', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Critical WordPress settings are protected against tampering from other WordPress admins.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Critical WordPress settings aren't protected against tampering from other WordPress admins.", 'wp-simple-firewall' );
	}
}