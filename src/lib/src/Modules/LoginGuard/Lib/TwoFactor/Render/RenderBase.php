<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Render;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string $plain_login_nonce
 * @property string $interim_login
 * @property string $rememberme
 * @property string $redirect_to
 * @property string $msg_error
 * @property string $interim_message
 */
abstract class RenderBase extends DynPropertiesClass {

	use Shield\Modules\ModConsumer;
	use Shield\Utilities\Consumer\WpUserConsumer;

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {
			case 'plain_login_nonce':
			case 'msg_error':
			case 'interim_message':
				$value = (string)$value;
				break;
			default:
				break;
		}
		return $value;
	}

	public function render() {
		echo $this->buildPage();
		die();
	}

	abstract protected function buildPage() :string;

	protected function getCommonFormData() :array {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		$mfaCon = $mod->getMfaController();
		/** @var LoginGuard\Options $opts */
		$opts = $mfaCon->getOptions();
		$con = $mfaCon->getCon();
		$req = Services::Request();
		$user = $this->getWpUser();
		$WP = Services::WpGeneral();

		$mfaSkip = (int)( $opts->getMfaSkip()/DAY_IN_SECONDS );

		return [
			'hrefs'   => [
				'form_action' => add_query_arg( array_filter( [
					'shield_action' => 'wp_login_2fa_verify',
					'wpe-login'     => ( function_exists( 'getenv' ) && @getenv( 'IS_WPE' ) ) ? 'true' : false
				] ), $WP->getLoginUrl() ),
			],
			'flags'   => [
				'can_skip_mfa'       => $opts->isMfaSkip(),
				'show_branded_links' => !$con->getModule_SecAdmin()->getWhiteLabelController()->isEnabled(),
			],
			'strings' => [
				'cancel'          => __( 'Cancel Login', 'wp-simple-firewall' ),
				'time_remaining'  => __( 'Time Remaining', 'wp-simple-firewall' ),
				'calculating'     => __( 'Calculating', 'wp-simple-firewall' ).' ...',
				'seconds'         => strtolower( __( 'Seconds', 'wp-simple-firewall' ) ),
				'login_expired'   => __( 'Login Expired', 'wp-simple-firewall' ),
				'verify_my_login' => __( 'Verify My Login', 'wp-simple-firewall' ),
				'skip_mfa'        => sprintf(
					__( "Remember me for %s", 'wp-simple-firewall' ),
					sprintf( _n( '%s day', '%s days', $mfaSkip, 'wp-simple-firewall' ), $mfaSkip )
				)
			],
			'vars'    => [
				'form_hidden_fields' => $this->getHiddenFields(),
				'login_fields'       => array_filter( array_map(
					function ( $provider ) {
						return $provider->getFormField();
					},
					$mfaCon->getProvidersForUser( $user, true )
				) ),
				'show_branded_links' => !$con->getModule_SecAdmin()->getWhiteLabelController()->isEnabled(),
				'time_remaining'     => $this->getLoginIntentExpiresAt() - $req->ts(),
				'message_type'       => 'info',
			]
		];
	}

	protected function getHiddenFields() :array {
		$req = Services::Request();

		$referUrl = $req->server( 'HTTP_REFERER', '' );
		if ( strpos( $referUrl, '?' ) ) {
			list( $referUrl, $referQuery ) = explode( '?', $referUrl, 2 );
		}
		else {
			$referQuery = '';
		}

		$redirectTo = $this->redirect_to;
		if ( empty( $redirectTo ) ) {

			if ( !empty( $referQuery ) ) {
				parse_str( $referQuery, $referQueryItems );
				if ( !empty( $referQueryItems[ 'redirect_to' ] ) ) {
					$redirectTo = $referQueryItems[ 'redirect_to' ];
				}
			}

			if ( empty( $redirectTo ) ) {
				$redirectTo = $req->getPath();
			}
		}

		$cancelHref = $req->post( 'cancel_href', '' );
		if ( empty( $cancelHref ) && Services::Data()->isValidWebUrl( $referUrl ) ) {
			$cancelHref = parse_url( $referUrl, PHP_URL_PATH );
		}

		global $interim_login;

		$fields = array_filter( [
			'interim-login' => ( $interim_login || $this->interim_login ) ? '1' : false,
			'login_nonce'   => $this->plain_login_nonce,
			'rememberme'    => esc_attr( $this->rememberme ),
			'redirect_to'   => esc_attr( esc_url( $redirectTo ) ),
			'cancel_href'   => esc_attr( esc_url( $cancelHref ) ),
			/**
			 * This server produced HTTP 402 error if the request to the login form didn't include wp-submit
			 * https://secure.helpscout.net/conversation/1781553925/1153
			 */
			'wp-submit'     => 'Complete Login',
		] );
		$fields[ 'wp_user_id' ] = $this->getWpUser()->ID;
		return $fields;
	}

	protected function getLoginIntentExpiresAt() :int {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		$mfaCon = $mod->getMfaController();
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();

		$intentAt = $mfaCon->getActiveLoginIntents( $this->getWpUser() )
					[ $mfaCon->findHashedNonce( $this->getWpUser(), $this->plain_login_nonce ) ][ 'start' ] ?? 0;
		return Services::Request()
					   ->carbon()
					   ->setTimestamp( $intentAt )
					   ->addMinutes( $opts->getLoginIntentMinutes() )->timestamp;
	}
}