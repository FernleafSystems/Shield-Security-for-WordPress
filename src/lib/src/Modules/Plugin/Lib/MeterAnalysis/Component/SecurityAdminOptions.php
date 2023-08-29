<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Options;

class SecurityAdminOptions extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'security_admin_options';
	public const WEIGHT = 3;

	protected function getOptConfigKey() :string {
		return 'admin_access_restrict_options';
	}

	protected function testIfProtected() :bool {
		$mod = self::con()->getModule_SecAdmin();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled()
			   && $mod->getSecurityAdminController()->isEnabledSecAdmin()
			   && $opts->isRestrictWpOptions();
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