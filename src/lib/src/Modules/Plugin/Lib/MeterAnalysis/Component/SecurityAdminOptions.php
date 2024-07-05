<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class SecurityAdminOptions extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'security_admin_options';
	public const WEIGHT = 3;

	protected function getOptConfigKey() :string {
		return 'admin_access_restrict_options';
	}

	protected function testIfProtected() :bool {
		return self::con()->comps->sec_admin->isEnabledSecAdmin() && self::con()->opts->optIs( 'admin_access_restrict_options', 'Y' );
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