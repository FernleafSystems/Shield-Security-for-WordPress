<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class PwnedPasswords extends Base {

	public function title() :string {
		return __( 'Block Pwned Passwords', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Prevent use of Pwned Passwords.', 'wp-simple-firewall' );
	}

	public function description() :array {
		return [
		];
	}

	public function getOptions() :array {
		return \array_merge( parent::getOptions(), [
			'enable_password_policies'
		] );
	}

	public function enabledStatus() :string {
		return ( self::con()->comps->opts_lookup->optIsAndModForOptEnabled( 'pass_prevent_pwned', 'Y' )
				 && self::con()->comps->opts_lookup->optIsAndModForOptEnabled( 'enable_password_policies', 'Y' ) )
			? EnumEnabledStatus::GOOD : EnumEnabledStatus::BAD;
	}
}