<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class PasswordStrength extends Base {

	public function title() :string {
		return __( 'Enforce Minimum Password Strength', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Enforce strong passwords for all users.', 'wp-simple-firewall' );
	}

	protected function tooltip() :string {
		return __( 'Edit settings to apply minimum password strength', 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$opts = self::con()->opts;

		$status = parent::status();

		if ( $opts->optGet( 'pass_min_strength' ) >= 3 && $opts->optIs( 'enable_password_policies', 'Y' ) ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			if ( !$opts->optIs( 'enable_password_policies', 'Y' ) ) {
				$status[ 'exp' ][] = __( 'All password policies are disabled.', 'wp-simple-firewall' );
			}
			$status[ 'exp' ][] = __( 'Users are allowed to set weak passwords.', 'wp-simple-firewall' );
		}

		return $status;
	}
}