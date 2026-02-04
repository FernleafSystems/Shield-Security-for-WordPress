<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class UserSuspendInactive extends Base {

	use Traits\OptConfigBased;

	public const MINIMUM_EDITION = 'business';
	public const SLUG = 'user_suspend_inactive';
	public const WEIGHT = 2;

	protected function getOptConfigKey() :string {
		return 'auto_idle_days';
	}

	protected function testIfProtected() :bool {
		return self::con()->opts->optGet( 'auto_idle_days' ) > 0;
	}

	public function title() :string {
		return __( 'Inactive User Accounts', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return sprintf( __( 'Inactive user accounts are automatically suspended after %s.', 'wp-simple-firewall' ),
			self::con()->opts->optGet( 'auto_idle_days' ) );
	}

	public function descUnprotected() :string {
		return __( 'There is currently no control over how inactive user accounts are handled.', 'wp-simple-firewall' );
	}
}