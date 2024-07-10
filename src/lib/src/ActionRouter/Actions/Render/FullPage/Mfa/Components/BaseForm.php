<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Mfa\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MfaLoginVerifyStep;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
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
		return [
			'content' => [
				'login_fields' => \array_filter( \array_map(
					function ( $provider ) {
						return $provider->renderLoginIntentFormField( self::con()->opts->optGet( 'mfa_verify_page' ) );
					},
					$mfaCon->getProvidersActiveForUser(
						Services::WpUsers()->getUserById( $this->action_data[ 'user_id' ] )
					)
				) ),
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
				'time_remaining'  => __( 'Time Remaining', 'wp-simple-firewall' ),
				'calculating'     => __( 'Calculating', 'wp-simple-firewall' ).' ...',
				'verify_my_login' => __( 'Verify My Login', 'wp-simple-firewall' ),
				'skip_mfa'        => sprintf(
					__( "Remember me for %s", 'wp-simple-firewall' ),
					sprintf( _n( '%s day', '%s days', $mfaSkip, 'wp-simple-firewall' ), $mfaSkip )
				)
			],
			'vars'    => [
				'form_hidden_fields' => $this->getHiddenFields(),
				'show_branded_links' => !$con->comps->whitelabel->isEnabled(),
				'message_type'       => 'info',
			]
		];
	}

	protected function getHiddenFields() :array {
		$req = Services::Request();

		$referUrl = $req->server( 'HTTP_REFERER', '' );
		if ( \strpos( $referUrl, '?' ) ) {
			[ $referUrl, $referQuery ] = \explode( '?', $referUrl, 2 );
		}
		else {
			$referQuery = '';
		}

		$redirectTo = $this->action_data[ 'redirect_to' ] ?? '';
		if ( empty( $redirectTo ) ) {

			if ( !empty( $referQuery ) ) {
				\parse_str( $referQuery, $referQueryItems );
				if ( !empty( $referQueryItems[ 'redirect_to' ] ) ) {
					$redirectTo = $referQueryItems[ 'redirect_to' ];
				}
			}

			if ( empty( $redirectTo ) ) {
				$redirectTo = $req->getPath();
			}
		}

		$cancelHref = $this->action_data[ 'cancel_href' ] ?? '';
		if ( empty( $cancelHref ) && Services::Data()->isValidWebUrl( $referUrl ) ) {
			$cancelHref = \wp_parse_url( $referUrl, \PHP_URL_PATH );
		}

		global $interim_login;

		$fields = \array_filter( [
			'interim-login' => ( $interim_login || ( $this->action_data[ 'interim_login' ] ?? '0' ) ) ? '1' : false,
			'login_nonce'   => $this->action_data[ 'plain_login_nonce' ],
			'rememberme'    => esc_attr( $this->action_data[ 'rememberme' ] ),
			'redirect_to'   => esc_attr( esc_url_raw( $redirectTo ) ),
			'cancel_href'   => esc_attr( esc_url_raw( $cancelHref ) ),
			/**
			 * This server produced HTTP 402 error if the request to the login form didn't include wp-submit
			 * https://secure.helpscout.net/conversation/1781553925/1153
			 */
			'wp-submit'     => 'Complete Login',
		] );
		$fields[ 'wp_user_id' ] = $this->action_data[ 'user_id' ];
		return $fields;
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