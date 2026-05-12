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

	protected function configureStatus() :array {
		$state = $this->formProtectionState();
		$silentCaptcha = self::con()->labels->getBrandName( 'silentcaptcha' );
		$status = parent::status();

		if ( empty( $state[ 'protected_forms' ][ 'login' ] ) ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = sprintf( __( "%s bot detection isn't protecting against brute-force attacks on your WordPress login.", 'wp-simple-firewall' ), $silentCaptcha );
		}
		if ( empty( $state[ 'protected_forms' ][ 'register' ] ) ) {
			$status[ 'exp' ][] = sprintf( __( "%s bot detection isn't protecting your WordPress registration.", 'wp-simple-firewall' ), $silentCaptcha );
		}
		if ( empty( $state[ 'protected_forms' ][ 'password' ] ) ) {
			$status[ 'exp' ][] = sprintf( __( "%s bot detection isn't protecting your WordPress lost password form.", 'wp-simple-firewall' ), $silentCaptcha );
		}
		if ( !$state[ 'cooldown_enabled' ] ) {
			$status[ 'exp' ][] = __( 'Login cooldown is not limiting repeated login attempts.', 'wp-simple-firewall' );
		}
		if ( !empty( $state[ 'unprotected_providers' ] ) ) {
			$status[ 'exp' ][] = sprintf(
				__( "Some installed third-party login forms aren't protected: %s", 'wp-simple-firewall' ),
				\implode( ', ', $state[ 'unprotected_providers' ] )
			);
		}

		if ( $status[ 'level' ] !== EnumEnabledStatus::BAD ) {
			$status[ 'level' ] = empty( $status[ 'exp' ] ) ? EnumEnabledStatus::GOOD : EnumEnabledStatus::OKAY;
		}

		return $status;
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$state = $this->formProtectionState();
		$status = parent::status();
		$silentCaptcha = self::con()->labels->getBrandName( 'silentcaptcha' );

		if ( !empty( $state[ 'protected_forms' ][ 'login' ] ) ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		else {
			$status[ 'exp' ][] = sprintf( __( "%s Bot Detection isn't protecting against brute-force attacks on your WordPress login.", 'wp-simple-firewall' ), $silentCaptcha );
			if ( !empty( $state[ 'protected_forms' ][ 'register' ] ) ) {
				$status[ 'level' ] = EnumEnabledStatus::OKAY;
			}
			else {
				$status[ 'level' ] = EnumEnabledStatus::BAD;
				$status[ 'exp' ][] = sprintf( __( "%s Bot Detection isn't protecting your WordPress registration.", 'wp-simple-firewall' ), $silentCaptcha );
			}
			if ( empty( $state[ 'protected_forms' ][ 'password' ] ) ) {
				$status[ 'exp' ][] = sprintf( __( "%s Bot Detection isn't protecting your WordPress lost password form.", 'wp-simple-firewall' ), $silentCaptcha );
			}
		}

		return $status;
	}

	public function postureSignals() :array {
		$state = $this->formProtectionState();
		$signals = [];

		foreach ( $this->formDefinitions() as $formKey => $definition ) {
			$protected = !empty( $state[ 'protected_forms' ][ $formKey ] );
			$signals[] = $this->buildPostureSignal(
				'ade_'.$formKey,
				$definition[ 'title' ],
				$definition[ 'weight' ],
				$protected ? $definition[ 'weight' ] : 0,
				$protected ? 'good' : 'critical',
				$protected,
				[
					$protected ? $definition[ 'enabled_message' ] : $definition[ 'disabled_message' ],
				]
			);
		}

		$signals[] = $this->buildPostureSignal(
			'login_cooldown',
			__( 'Login Cooldown', 'wp-simple-firewall' ),
			4,
			$state[ 'cooldown_enabled' ] ? 4 : 0,
			$state[ 'cooldown_enabled' ] ? 'good' : 'critical',
			$state[ 'cooldown_enabled' ],
			[
				$state[ 'cooldown_enabled' ]
					? __( 'Login cooldown is limiting repeated login attempts.', 'wp-simple-firewall' )
					: __( 'Login cooldown is not limiting repeated login attempts.', 'wp-simple-firewall' ),
			]
		);

		if ( $state[ 'has_third_party_forms' ] ) {
			$signals[] = $this->buildPostureSignal(
				'login_forms_third_parties',
				__( '3rd Party Login Forms', 'wp-simple-firewall' ),
				3,
				empty( $state[ 'unprotected_providers' ] ) ? 3 : 0,
				empty( $state[ 'unprotected_providers' ] ) ? 'good' : 'critical',
				empty( $state[ 'unprotected_providers' ] ),
				[
					empty( $state[ 'unprotected_providers' ] )
						? __( "Installed third-party login forms are protected against bots.", 'wp-simple-firewall' )
						: sprintf( __( "Some installed third-party login forms aren't protected: %s", 'wp-simple-firewall' ), \implode( ', ', $state[ 'unprotected_providers' ] ) ),
				]
			);
		}

		return $signals;
	}

	/**
	 * @return array<string,array{weight:int,title:string,enabled_message:string,disabled_message:string}>
	 */
	private function formDefinitions() :array {
		return [
			'login' => [
				'weight'           => 5,
				'title'            => __( 'Protect Login Form', 'wp-simple-firewall' ),
				'enabled_message'  => __( 'The login form is protected against brute-force bots.', 'wp-simple-firewall' ),
				'disabled_message' => __( 'The login form is not protected against brute-force bots.', 'wp-simple-firewall' ),
			],
			'register' => [
				'weight'           => 3,
				'title'            => __( 'Protect Registration Form', 'wp-simple-firewall' ),
				'enabled_message'  => __( 'The registration form is protected against brute-force bots.', 'wp-simple-firewall' ),
				'disabled_message' => __( 'The registration form is not protected against brute-force bots.', 'wp-simple-firewall' ),
			],
			'password' => [
				'weight'           => 2,
				'title'            => __( 'Protect Lost Password Form', 'wp-simple-firewall' ),
				'enabled_message'  => __( 'The lost password form is protected against brute-force bots.', 'wp-simple-firewall' ),
				'disabled_message' => __( 'The lost password form is not protected against brute-force bots.', 'wp-simple-firewall' ),
			],
		];
	}

	/**
	 * @return array{
	 *   protected_forms:array<string,bool>,
	 *   cooldown_enabled:bool,
	 *   has_third_party_forms:bool,
	 *   unprotected_providers:list<string>
	 * }
	 */
	private function formProtectionState() :array {
		$forms = self::con()->opts->optGet( 'bot_protection_locations' );
		$forms = \is_array( $forms ) ? \array_values( \array_filter( $forms, 'is_string' ) ) : [];
		$installed = self::con()->comps->forms_users->getInstalled();
		unset( $installed[ WordPress::Slug() ] );
		$protectedForms = [];
		foreach ( \array_keys( $this->formDefinitions() ) as $formKey ) {
			$protectedForms[ $formKey ] = \in_array( $formKey, $forms, true );
		}
		$unprotectedProviders = \array_values( \array_filter( \array_map(
			function ( string $providerClass ) {
				$provider = new $providerClass();
				return $provider->isEnabled() ? null : $provider->getHandlerName();
			},
			$installed
		) ) );

		return [
			'protected_forms'      => $protectedForms,
			'cooldown_enabled'     => (int)self::con()->opts->optGet( 'login_limit_interval' ) > 0,
			'has_third_party_forms'=> \count( $installed ) > 0,
			'unprotected_providers'=> $unprotectedProviders,
		];
	}
}
