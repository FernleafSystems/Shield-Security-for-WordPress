<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;
use FernleafSystems\Wordpress\Services\Services;

class LoginIntentPage {

	use MfaControllerConsumer;

	public function loadPage() {
		echo $this->renderPage();
	}

	public function renderForm() :string {
		$mfaCon = $this->getMfaCon();
		/** @var LoginGuard\ModCon $mod */
		$mod = $mfaCon->getMod();
		/** @var LoginGuard\Options $opts */
		$opts = $mfaCon->getOptions();
		$con = $mfaCon->getCon();
		$req = Services::Request();
		$WP = Services::WpGeneral();

		$notice = $con->getAdminNotices()->getFlashNotice();
		if ( $notice instanceof NoticeVO ) {
			$msg = $notice->render_data[ 'message' ];
		}
		else {
			$msg = __( 'Please supply at least 1 authentication code', 'wp-simple-firewall' );
		}

		if ( !empty( $msg ) && !$con->getModule_SecAdmin()->getWhiteLabelController()->isEnabled() ) {
			$msg .= sprintf( ' [<a href="%s" target="_blank">%s</a>]', 'https://shsec.io/shieldcantaccess', __( 'More Info', 'wp-simple-firewall' ) );
		}

		$referUrl = $req->server( 'HTTP_REFERER', '' );
		if ( strpos( $referUrl, '?' ) ) {
			list( $referUrl, $referQuery ) = explode( '?', $referUrl, 2 );
		}
		else {
			$referQuery = '';
		}

		$redirectTo = '';
		if ( !empty( $referQuery ) ) {
			parse_str( $referQuery, $aReferQueryItems );
			if ( !empty( $aReferQueryItems[ 'redirect_to' ] ) ) {
				$redirectTo = rawurlencode( $aReferQueryItems[ 'redirect_to' ] );
			}
		}
		if ( empty( $redirectTo ) ) {
			$redirectTo = rawurlencode( $req->post( 'redirect_to', $req->getUri() ) );
		}

		$cancelHref = $req->post( 'cancel_href', '' );
		if ( empty( $cancelHref ) && Services::Data()->isValidWebUrl( $referUrl ) ) {
			$cancelHref = parse_url( $referUrl, PHP_URL_PATH );
		}

		$nMfaSkip = (int)( $opts->getMfaSkip()/DAY_IN_SECONDS );
		$timeRemaining = $mfaCon->getLoginIntentExpiresAt() - $req->ts();

		$data = [
			'strings' => [
				'cancel'          => __( 'Cancel Login', 'wp-simple-firewall' ),
				'time_remaining'  => __( 'Time Remaining', 'wp-simple-firewall' ),
				'calculating'     => __( 'Calculating', 'wp-simple-firewall' ).' ...',
				'seconds'         => strtolower( __( 'Seconds', 'wp-simple-firewall' ) ),
				'login_expired'   => __( 'Login Expired', 'wp-simple-firewall' ),
				'verify_my_login' => __( 'Verify My Login', 'wp-simple-firewall' ),
				'message'         => $msg,
				'skip_mfa'        => sprintf(
					__( "Don't ask again on this browser for %s.", 'wp-simple-firewall' ),
					sprintf( _n( '%s day', '%s days', $nMfaSkip, 'wp-simple-firewall' ), $nMfaSkip )
				)
			],
			'data'    => [
				'login_fields'      => array_filter( array_map(
					function ( $oProvider ) {
						return $oProvider->getFormField();
					},
					$mfaCon->getProvidersForUser( Services::WpUsers()->getCurrentWpUser(), true )
				) ),
				'time_remaining'    => $timeRemaining,
				'message_type'      => 'info',
				'login_intent_flag' => $mod->getLoginIntentRequestFlag(),
			],
			'hrefs'   => [
				'form_action' => parse_url( $WP->getAdminUrl( '', true ), PHP_URL_PATH ),
				'redirect_to' => $redirectTo,
				'cancel_href' => $cancelHref
			],
			'flags'   => [
				'can_skip_mfa'       => $opts->isMfaSkip(),
				'show_branded_links' => !$con->getModule_SecAdmin()->getWhiteLabelController()->isEnabled(),
			]
		];

		return $mod->renderTemplate(
			'/snippets/login_intent/form.twig',
			Services::DataManipulation()->mergeArraysRecursive(
				$mod->getUIHandler()->getBaseDisplayData(), $data ),
			true
		);
	}

	private function renderPage() :string {
		$IC = $this->getMfaCon();
		/** @var LoginGuard\ModCon $mod */
		$mod = $IC->getMod();
		$con = $IC->getCon();
		$req = Services::Request();

		$labels = $con->getLabels();
		$bannerURL = empty( $labels[ 'url_login2fa_logourl' ] ) ? $con->urls->forImage( 'shield/banner-2FA.png' ) : $labels[ 'url_login2fa_logourl' ];
		$timeRemaining = $IC->getLoginIntentExpiresAt() - $req->ts();

		$data = [
			'strings' => [
				'what_is_this' => __( 'What is this?', 'wp-simple-firewall' ),
				'page_title'   => sprintf( __( '%s Login Verification', 'wp-simple-firewall' ), $con->getHumanName() ),
			],
			'data'    => [
				'time_remaining' => $timeRemaining,
			],
			'hrefs'   => [
				'css_bootstrap' => $con->urls->forCss( 'bootstrap' ),
				'js_bootstrap'  => $con->urls->forJs( 'bootstrap' ),
				'shield_logo'   => 'https://ps.w.org/wp-simple-firewall/assets/banner-772x250.png',
				'what_is_this'  => 'https://help.getshieldsecurity.com/article/322-what-is-the-login-authentication-portal',
			],
			'imgs'    => [
				'banner'  => $bannerURL,
				'favicon' => $con->urls->forImage( 'pluginlogo_24x24.png' ),
			],
			'flags'   => [
				'show_branded_links' => !$con->getModule_SecAdmin()->getWhiteLabelController()->isEnabled(),
			],
			'content' => [
				'form' => $this->renderForm(),
			]
		];

		// Provide the U2F scripts if required.
		$data[ 'head' ] = [
			'scripts' => [
				[
					'src' => $con->urls->forJs( 'u2f-bundle.js' ),
				],
				[
					'src' => $con->urls->forJs( 'shield/login2fa.js' ),
				]
			]
		];

		return $mod->renderTemplate( '/pages/login_intent/index.twig',
			Services::DataManipulation()->mergeArraysRecursive(
				$mod->getUIHandler()->getBaseDisplayData(), $data ), true );
	}
}