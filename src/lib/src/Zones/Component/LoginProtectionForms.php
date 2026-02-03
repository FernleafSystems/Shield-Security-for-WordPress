<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class LoginProtectionForms extends Base {

	public function title() :string {
		return sprintf( '%s: %s',
			__( 'Limit Attempts', 'wp-simple-firewall' ),
			__( 'Login, Register & Lost Password Forms', 'wp-simple-firewall' )
		);
	}

	public function subtitle() :string {
		return sprintf( __( 'Select which user forms should be protected against brute-force attacks.', 'wp-simple-firewall' ), self::con()->labels->Name );
	}

	protected function tooltip() :string {
		return __( 'Edit settings that apply protection to your login & user forms', 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$forms = self::con()->opts->optGet( 'bot_protection_locations' );

		$status = parent::status();

		if ( \in_array( 'login', $forms ) ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		else {
			$silentCaptcha = self::con()->labels->getBrandName( 'silentcaptcha' );
			$status[ 'exp' ][] = sprintf( __( "%s Bot Detection isn't protecting against brute-force attacks on your WordPress login.", 'wp-simple-firewall' ), $silentCaptcha );
			if ( \in_array( 'register', $forms ) ) {
				$status[ 'level' ] = EnumEnabledStatus::OKAY;
			}
			else {
				$status[ 'level' ] = EnumEnabledStatus::BAD;
				$status[ 'exp' ][] = sprintf( __( "%s Bot Detection isn't protecting your WordPress registration.", 'wp-simple-firewall' ), $silentCaptcha );
			}
			if ( !\in_array( 'password', $forms ) ) {
				$status[ 'exp' ][] = sprintf( __( "%s Bot Detection isn't protecting your WordPress lost password form.", 'wp-simple-firewall' ), $silentCaptcha );
			}
		}

		return $status;
	}
}