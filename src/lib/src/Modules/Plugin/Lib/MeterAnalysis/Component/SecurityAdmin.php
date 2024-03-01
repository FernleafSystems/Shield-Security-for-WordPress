<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class SecurityAdmin extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'security_admin';
	public const WEIGHT = 5;

	protected function getOptConfigKey() :string {
		return 'admin_access_key';
	}

	protected function testIfProtected() :bool {
		return self::con()->comps->sec_admin->isEnabledSecAdmin();
	}

	public function title() :string {
		return __( 'Security Admin Protection', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'The security plugin is protected against tampering.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "The security plugin isn't protected against tampering.", 'wp-simple-firewall' );
	}
}