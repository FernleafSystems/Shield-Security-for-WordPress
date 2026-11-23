<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\StringsOptions;
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

	protected function configureStatus() :array {
		$state = $this->formProtectionState();
		$status = parent::status();

		if ( !$state[ 'protected_forms' ][ 'login' ] ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
		}
		$unprotected = $this->unprotectedFormsExplanation( $state );
		if ( $unprotected !== '' ) {
			$status[ 'exp' ][] = $this->explanationFixedBy(
				$unprotected,
				$state[ 'wordpress_selected' ] ? 'bot_protection_locations' : 'user_form_providers'
			);
		}
		if ( !$state[ 'cooldown_enabled' ] ) {
			$status[ 'exp' ][] = $this->explanationFixedBy( __( 'Login cooldown is not limiting repeated login attempts.', 'wp-simple-firewall' ), 'login_limit_interval' );
		}
		if ( !empty( $state[ 'unprotected_providers' ] ) ) {
			$status[ 'exp' ][] = $this->explanationFixedBy( sprintf(
				__( "Some installed third-party login forms aren't protected: %s", 'wp-simple-firewall' ),
				\implode( ', ', $state[ 'unprotected_providers' ] )
			), 'user_form_providers' );
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

		if ( $state[ 'protected_forms' ][ 'login' ] ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		else {
			$status[ 'level' ] = $state[ 'protected_forms' ][ 'register' ] ? EnumEnabledStatus::OKAY : EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = $this->unprotectedFormsExplanation( $state );
		}

		return $status;
	}

	/**
	 * One explanation naming every WordPress form that silentCAPTCHA isn't protecting, or '' when it protects all.
	 * @param array{protected_forms:array{login:bool,register:bool,password:bool},wordpress_selected:bool} $state
	 */
	private function unprotectedFormsExplanation( array $state ) :string {
		$protectedForms = $state[ 'protected_forms' ];
		$names = [
			'login'    => __( 'login', 'wp-simple-firewall' ),
			'register' => __( 'registration', 'wp-simple-firewall' ),
			'password' => __( 'lost password', 'wp-simple-firewall' ),
		];
		$unprotected = [];
		foreach ( $protectedForms as $form => $isProtected ) {
			if ( !$isProtected ) {
				$unprotected[] = $names[ $form ];
			}
		}
		$count = \count( $unprotected );
		if ( $count === 0 ) {
			return '';
		}

		$last = \array_pop( $unprotected );
		$list = empty( $unprotected ) ? $last : sprintf(
			/* translators: 1: comma-separated form names, 2: the last form name */
			__( '%1$s or %2$s', 'wp-simple-firewall' ),
			\implode( __( ', ', 'wp-simple-firewall' ), $unprotected ),
			$last
		);

		$explanation = sprintf(
			/* translators: 1: silentCAPTCHA brand name, 2: list of WordPress form names */
			_n(
				"%1\$s bot detection isn't protecting your WordPress %2\$s form.",
				"%1\$s bot detection isn't protecting your WordPress %2\$s forms.",
				$count,
				'wp-simple-firewall'
			),
			self::con()->labels->getBrandName( 'silentcaptcha' ),
			$list
		);
		if ( !$state[ 'wordpress_selected' ] ) {
			$explanation .= ' '.sprintf(
				/* translators: %s: name of the setting that chooses which user forms providers are checked */
				__( "WordPress isn't selected in %s.", 'wp-simple-firewall' ),
				( new StringsOptions() )->getFor( 'user_form_providers' )[ 'name' ]
			);
		}
		return $explanation;
	}

	/**
	 * @return array{
	 *   protected_forms:array{login:bool,register:bool,password:bool},
	 *   wordpress_selected:bool,
	 *   cooldown_enabled:bool,
	 *   unprotected_providers:list<string>
	 * }
	 */
	private function formProtectionState() :array {
		$forms = self::con()->opts->optGet( 'bot_protection_locations' );
		$forms = \is_array( $forms ) ? \array_values( \array_filter( $forms, 'is_string' ) ) : [];
		// WordPress's own forms are a provider too: unticked, none of them is checked whatever the locations say.
		$wordpressSelected = \in_array( WordPress::Slug(), self::con()->comps->forms_users->getSelectedProviders(), true );
		$installed = self::con()->comps->forms_users->getInstalled();
		unset( $installed[ WordPress::Slug() ] );
		$protectedForms = [
			'login'    => $wordpressSelected && \in_array( 'login', $forms, true ),
			'register' => $wordpressSelected && \in_array( 'register', $forms, true ),
			'password' => $wordpressSelected && \in_array( 'password', $forms, true ),
		];
		$unprotectedProviders = \array_values( \array_filter( \array_map(
			function ( string $providerClass ) {
				$provider = new $providerClass();
				return $provider->isEnabled() ? null : $provider->getHandlerName();
			},
			$installed
		) ) );

		return [
			'protected_forms'       => $protectedForms,
			'wordpress_selected'    => $wordpressSelected,
			'cooldown_enabled'      => (int)self::con()->opts->optGet( 'login_limit_interval' ) > 0,
			'unprotected_providers'=> $unprotectedProviders,
		];
	}
}
