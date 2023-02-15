<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Mfa\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MfaLoginVerifyStep;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
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
		$con = $this->getCon();
		$mod = $con->getModule_LoginGuard();
		/** @var LoginGuard\Options $opts */
		$opts = $mod->getOptions();
		$WP = Services::WpGeneral();

		$mfaSkip = (int)( $opts->getMfaSkip()/DAY_IN_SECONDS );

		return [
			'content' => [
				'login_fields' => array_filter( array_map(
					function ( $provider ) use ( $opts ) {
						return $provider->renderLoginIntentFormField( $opts->getMfaLoginIntentFormat() );
					},
					$mod->getMfaController()->getProvidersActiveForUser( $this->getWpUser() )
				) ),
			],
			'flags'   => [
				'can_skip_mfa'       => $opts->isMfaSkip(),
				'show_branded_links' => !$con->getModule_SecAdmin()->getWhiteLabelController()->isEnabled(),
			],
			'hrefs'   => [
				'form_action' => $con->plugin_urls->noncedPluginAction( MfaLoginVerifyStep::class, $WP->getLoginUrl(), [
					'wpe-login' => ( function_exists( 'getenv' ) && @getenv( 'IS_WPE' ) ) ? 'true' : false
				] ),
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
				'show_branded_links' => !$con->getModule_SecAdmin()->getWhiteLabelController()->isEnabled(),
				'time_remaining'     => $this->getLoginIntentExpiresAt() - Services::Request()->ts(),
				'message_type'       => 'info',
			]
		];
	}

	protected function getHiddenFields() :array {
		$req = Services::Request();

		$referUrl = $req->server( 'HTTP_REFERER', '' );
		if ( strpos( $referUrl, '?' ) ) {
			[ $referUrl, $referQuery ] = explode( '?', $referUrl, 2 );
		}
		else {
			$referQuery = '';
		}

		$redirectTo = $this->action_data[ 'redirect_to' ] ?? '';
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
			'interim-login' => ( $interim_login || ( $this->action_data[ 'interim_login' ] ?? '0' ) ) ? '1' : false,
			'login_nonce'   => $this->action_data[ 'plain_login_nonce' ],
			'rememberme'    => esc_attr( $this->action_data[ 'rememberme' ] ),
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
		$mod = $this->getCon()->getModule_LoginGuard();
		$mfaCon = $mod->getMfaController();
		/** @var LoginGuard\Options $opts */
		$opts = $mod->getOptions();

		$intentAt = $mfaCon->getActiveLoginIntents( $this->getWpUser() )
					[ $mfaCon->findHashedNonce( $this->getWpUser(), $this->action_data[ 'plain_login_nonce' ] ) ][ 'start' ] ?? 0;
		return Services::Request()
					   ->carbon()
					   ->setTimestamp( $intentAt )
					   ->addMinutes( $opts->getLoginIntentMinutes() )->timestamp;
	}

	protected function getWpUser() :\WP_User {
		return Services::WpUsers()->getUserById( $this->action_data[ 'user_id' ] );
	}

	protected function getRequiredDataKeys() :array {
		return [
			'user_id',
			'plain_login_nonce',
			'rememberme',
		];
	}
}