<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Mfa\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MfaLoginVerifyStep;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\LoginRequestValues;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseForm extends Base {

	/**
	 * @throws ActionException
	 */
	protected function getAllRenderDataArrays() :array {
		$data = parent::getAllRenderDataArrays();
		$data[ 25 ] = $this->getCommonFormData();
		return $data;
	}

	protected function getCommonFormData() :array {
		$con = self::con();
		$mfaCon = $con->comps->mfa;
		$mfaSkip = (int)( $mfaCon->getMfaSkip()/\DAY_IN_SECONDS );
		$data = $this->loginIntentRenderData();
		$user = Services::WpUsers()->getUserById( $data[ 'user_id' ] );
		$providers = $user instanceof \WP_User ? $mfaCon->getProvidersActiveForUser( $user ) : [];
		return [
			'content' => [
				'login_fields' => \array_values( \array_filter( \array_map(
					function ( $p ) {
						$html = $p->renderLoginIntentFormField( self::con()->opts->optGet( 'mfa_verify_page' ) );
						return empty( $html ) ? null : [
							'slug'      => $p::ProviderSlug(),
							'name'      => $p::ProviderName(),
							'html'      => $html,
							'tab_icon'  => $this->getLoginFieldTabIcon( $p::ProviderSlug() ),
							'tab_label' => $this->getLoginFieldTabLabel( $p::ProviderSlug(), $p::ProviderName() ),
						];
					},
					$providers
				) ) ),
			],
			'flags'   => [
				'can_skip_mfa'       => $mfaCon->getMfaSkip() > 0,
				'show_branded_links' => !$con->comps->whitelabel->isEnabled(),
			],
			'hrefs'   => [
				'form_action' => $con->plugin_urls->noncedPluginAction(
					MfaLoginVerifyStep::class,
					Services::WpGeneral()->getLoginUrl(),
					[
						'wpe-login' => ( \function_exists( 'getenv' ) && @getenv( 'IS_WPE' ) ) ? 'true' : false
					]
				),
			],
			'strings' => [
				'cancel'          => __( 'Cancel Login', 'wp-simple-firewall' ),
				'calculating'     => __( 'Calculating', 'wp-simple-firewall' ).' ...',
				'verify_my_login' => __( 'Verify My Login', 'wp-simple-firewall' ),
				'skip_mfa'        => sprintf(
					__( "Remember me for %s", 'wp-simple-firewall' ),
					sprintf( _n( '%s day', '%s days', $mfaSkip, 'wp-simple-firewall' ), $mfaSkip )
				)
			],
			'vars'    => [
				'form_hidden_fields' => $this->getHiddenFields(),
			]
		];
	}

	protected function getHiddenFields() :array {
		$req = Services::Request();
		$data = $this->loginIntentRenderData();
		$referUrl = $req->server( 'HTTP_REFERER', '' );
		$referUrl = \is_string( $referUrl ) ? $referUrl : '';
		$cancelHref = $data[ 'cancel_href' ];
		if ( $cancelHref === '' && Services::Data()->isValidWebUrl( $referUrl ) ) {
			$cancelHref = LoginRequestValues::safeRedirect( \wp_parse_url( $referUrl, \PHP_URL_PATH ), '' );
		}

		global $interim_login;

		$fields = \array_filter( [
			'interim-login' => ( $interim_login === true || $data[ 'interim_login' ] === '1' ) ? '1' : false,
			'login_nonce'   => esc_attr( $data[ 'plain_login_nonce' ] ),
			'rememberme'    => esc_attr( $data[ 'rememberme' ] ),
			'redirect_to'   => esc_attr( esc_url_raw( $data[ 'redirect_to' ] ) ),
			'cancel_href'   => esc_attr( esc_url_raw( $cancelHref ) ),
			/**
			 * This server produced HTTP 402 error if the request to the login form didn't include wp-submit
			 * https://secure.helpscout.net/conversation/1781553925/1153
			 */
			'wp-submit'     => __( 'Complete Login', 'wp-simple-firewall' ),
		] );
		$fields[ 'wp_user_id' ] = $data[ 'user_id' ];
		return $fields;
	}

	private function getLoginFieldTabIcon( string $slug ) :string {
		return [
			'email'      => 'bi-envelope',
			'ga'         => 'bi-phone',
			'passkey'    => 'bi-fingerprint',
			'yubi'       => 'bi-key-fill',
			'sms'        => 'bi-chat-left-dots',
			'u2f'        => 'bi-usb-symbol',
			'backupcode' => 'bi-shield-lock',
		][ $slug ] ?? 'bi-shield-lock';
	}

	private function getLoginFieldTabLabel( string $slug, string $fallback ) :string {
		return [
			'email'      => __( 'Email', 'wp-simple-firewall' ),
			'ga'         => __( 'Authenticator', 'wp-simple-firewall' ),
			'passkey'    => __( 'Passkey', 'wp-simple-firewall' ),
			'yubi'       => __( 'YubiKey', 'wp-simple-firewall' ),
			'sms'        => __( 'SMS', 'wp-simple-firewall' ),
			'u2f'        => __( 'Security Key', 'wp-simple-firewall' ),
			'backupcode' => __( 'Backup code', 'wp-simple-firewall' ),
		][ $slug ] ?? $fallback;
	}
}
