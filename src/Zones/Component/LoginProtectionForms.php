<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers\WordPress;
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

	public function postureSignals() :array {
		$forms = self::con()->opts->optGet( 'bot_protection_locations' );
		$cooldown = (int)self::con()->opts->optGet( 'login_limit_interval' ) > 0;
		$installed = self::con()->comps->forms_users->getInstalled();
		unset( $installed[ WordPress::Slug() ] );
		$hasThirdPartyForms = \count( $installed ) > 0;
		$unprotectedProviders = \array_filter( \array_map(
			function ( string $providerClass ) {
				$provider = new $providerClass();
				return $provider->isEnabled() ? null : $provider->getHandlerName();
			},
			$installed
		) );

		$signals = [];
		foreach ( [
			'login'    => [ 'weight' => 5, 'title' => __( 'Protect Login Form', 'wp-simple-firewall' ) ],
			'register' => [ 'weight' => 3, 'title' => __( 'Protect Registration Form', 'wp-simple-firewall' ) ],
			'password' => [ 'weight' => 2, 'title' => __( 'Protect Lost Password Form', 'wp-simple-firewall' ) ],
		] as $formKey => $definition ) {
			$protected = \in_array( $formKey, $forms, true );
			$signals[] = $this->buildPostureSignal(
				'ade_'.$formKey,
				$definition[ 'title' ],
				$definition[ 'weight' ],
				$protected ? $definition[ 'weight' ] : 0,
				$protected ? 'good' : 'critical',
				$protected,
				[
					$protected
						? sprintf( __( 'The %s form is protected against brute-force bots.', 'wp-simple-firewall' ), strtolower( $definition[ 'title' ] ) )
						: sprintf( __( 'The %s form is not protected against brute-force bots.', 'wp-simple-firewall' ), strtolower( $definition[ 'title' ] ) ),
				]
			);
		}

		$signals[] = $this->buildPostureSignal(
			'login_cooldown',
			__( 'Login Cooldown', 'wp-simple-firewall' ),
			4,
			$cooldown ? 4 : 0,
			$cooldown ? 'good' : 'critical',
			$cooldown,
			[
				$cooldown
					? __( 'Login cooldown is limiting repeated login attempts.', 'wp-simple-firewall' )
					: __( 'Login cooldown is not limiting repeated login attempts.', 'wp-simple-firewall' ),
			]
		);

		if ( $hasThirdPartyForms ) {
			$signals[] = $this->buildPostureSignal(
				'login_forms_third_parties',
				__( '3rd Party Login Forms', 'wp-simple-firewall' ),
				3,
				empty( $unprotectedProviders ) ? 3 : 0,
				empty( $unprotectedProviders ) ? 'good' : 'critical',
				empty( $unprotectedProviders ),
				[
					empty( $unprotectedProviders )
						? __( "Installed third-party login forms are protected against bots.", 'wp-simple-firewall' )
						: sprintf( __( "Some installed third-party login forms aren't protected: %s", 'wp-simple-firewall' ), implode( ', ', $unprotectedProviders ) ),
				]
			);
		}

		return $signals;
	}
}
