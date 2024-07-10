<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class LimitLogin extends Base {

	public function title() :string {
		return __( 'Limit Login Attempts', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Protect the login page against bots and brute-force attacks.', 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$status = parent::status();

		if ( self::con()->comps->opts_lookup->enabledLoginGuardAntiBotCheck() ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( "silentCAPTCHA Bot Detection isn't running on your login page." );
		}

		if ( self::con()->opts->optGet( 'login_limit_interval' ) === 0 ) {
			$status[ 'exp' ][] = __( "Login cooldown, that helps prevent brute-force attacks on your login, is disabled." );
			if ( $status[ 'level' ] === EnumEnabledStatus::GOOD ) {
				$status[ 'level' ] = EnumEnabledStatus::OKAY;
			}
		}

		return $status;
	}
}