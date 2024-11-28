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

	protected function tooltip() :string {
		return __( "Edit settings to prevent use of 'pwned' passwords", 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$opts = self::con()->opts;

		$status = parent::status();

		if ( $opts->optIs( 'pass_prevent_pwned', 'Y' ) && $opts->optIs( 'enable_password_policies', 'Y' ) ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			if ( !$opts->optIs( 'enable_password_policies', 'Y' ) ) {
				$status[ 'exp' ][] = __( "All password policies are disabled.", 'wp-simple-firewall' );
			}
			$status[ 'exp' ][] = __( "Users are allowed to re-use compromised passwords.", 'wp-simple-firewall' );
		}

		return $status;
	}
}