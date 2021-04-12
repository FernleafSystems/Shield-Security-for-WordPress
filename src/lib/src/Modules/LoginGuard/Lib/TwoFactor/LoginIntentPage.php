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

	/**
	 * @return string
	 */
	public function renderForm() {
		$oIC = $this->getMfaCon();
		/** @var LoginGuard\ModCon $mod */
		$mod = $oIC->getMod();
		/** @var LoginGuard\Options $opts */
		$opts = $oIC->getOptions();
		$con = $oIC->getCon();
		$req = Services::Request();
		$WP = Services::WpGeneral();

		$oNotice = $con->getAdminNotices()->getFlashNotice();
		if ( $oNotice instanceof NoticeVO ) {
			$sMessage = $oNotice->render_data[ 'message' ];
		}
		else {
			$sMessage = $opts->isChainedAuth() ?
				__( 'Please supply all authentication codes', 'wp-simple-firewall' )
				: __( 'Please supply at least 1 authentication code', 'wp-simple-firewall' );
		}

		$sReferUrl = $req->server( 'HTTP_REFERER', '' );
		if ( strpos( $sReferUrl, '?' ) ) {
			list( $sReferUrl, $sReferQuery ) = explode( '?', $sReferUrl, 2 );
		}
		else {
			$sReferQuery = '';
		}

		$sRedirectTo = '';
		if ( !empty( $sReferQuery ) ) {
			parse_str( $sReferQuery, $aReferQueryItems );
			if ( !empty( $aReferQueryItems[ 'redirect_to' ] ) ) {
				$sRedirectTo = rawurlencode( $aReferQueryItems[ 'redirect_to' ] );
			}
		}
		if ( empty( $sRedirectTo ) ) {
			$sRedirectTo = rawurlencode( $req->post( 'redirect_to', $req->getUri() ) );
		}

		$sCancelHref = $req->post( 'cancel_href', '' );
		if ( empty( $sCancelHref ) && Services::Data()->isValidWebUrl( $sReferUrl ) ) {
			$sCancelHref = parse_url( $sReferUrl, PHP_URL_PATH );
		}

		$nMfaSkip = (int)( $opts->getMfaSkip()/DAY_IN_SECONDS );
		$nTimeRemaining = $mod->getSession()->login_intent_expires_at - $req->ts();
		$data = [
			'strings' => [
				'cancel'          => __( 'Cancel Login', 'wp-simple-firewall' ),
				'time_remaining'  => __( 'Time Remaining', 'wp-simple-firewall' ),
				'calculating'     => __( 'Calculating', 'wp-simple-firewall' ).' ...',
				'seconds'         => strtolower( __( 'Seconds', 'wp-simple-firewall' ) ),
				'login_expired'   => __( 'Login Expired', 'wp-simple-firewall' ),
				'verify_my_login' => __( 'Verify My Login', 'wp-simple-firewall' ),
				'message'         => $sMessage,
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
					$oIC->getProvidersForUser( Services::WpUsers()->getCurrentWpUser(), true )
				) ),
				'time_remaining'    => $nTimeRemaining,
				'message_type'      => 'info',
				'login_intent_flag' => $mod->getLoginIntentRequestFlag(),
			],
			'hrefs'   => [
				'form_action' => parse_url( $WP->getAdminUrl( '', true ), PHP_URL_PATH ),
				'redirect_to' => $sRedirectTo,
				'cancel_href' => $sCancelHref
			],
			'flags'   => [
				'can_skip_mfa'       => $opts->isMfaSkip(),
				'show_branded_links' => !$mod->isEnabledWhitelabel(), // white label mitigation
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
		$oIC = $this->getMfaCon();
		/** @var LoginGuard\ModCon $mod */
		$mod = $oIC->getMod();
		$con = $oIC->getCon();
		$req = Services::Request();

		$labels = $con->getLabels();
		$bannerURL = empty( $labels[ 'url_login2fa_logourl' ] ) ? $con->urls->forImage( 'shield/banner-2FA.png' ) : $labels[ 'url_login2fa_logourl' ];
		$nTimeRemaining = $mod->getSession()->login_intent_expires_at - $req->ts();
		$data = [
			'strings' => [
				'what_is_this' => __( 'What is this?', 'wp-simple-firewall' ),
				'page_title'   => sprintf( __( '%s Login Verification', 'wp-simple-firewall' ), $con->getHumanName() ),
			],
			'data'    => [
				'time_remaining' => $nTimeRemaining,
			],
			'hrefs'   => [
				'css_bootstrap' => $con->urls->forCss( 'bootstrap' ),
				'js_bootstrap'  => $con->urls->forJs( 'bootstrap' ),
				'shield_logo'   => 'https://ps.w.org/wp-simple-firewall/assets/banner-772x250.png',
				'what_is_this'  => 'https://support.getshieldsecurity.com/support/solutions/articles/3000064840',
			],
			'imgs'    => [
				'banner'  => $bannerURL,
				'favicon' => $con->urls->forImage( 'pluginlogo_24x24.png' ),
			],
			'flags'   => [
				'show_branded_links' => !$mod->isEnabledWhitelabel(), // white label mitigation
				'has_u2f'            => isset( $oIC->getProvidersForUser(
						Services::WpUsers()->getCurrentWpUser(), true )[ LoginGuard\Lib\TwoFactor\Provider\U2F::SLUG ] )
			],
			'content' => [
				'form' => $this->renderForm(),
			]
		];

		// Provide the U2F scripts if required.
		if ( $data[ 'flags' ][ 'has_u2f' ] ) {
			$data[ 'head' ] = [
				'scripts' => [
					[
						'src' => $con->urls->forJs( 'u2f-bundle.js' ),
					],
					[
						'src' => $con->urls->forJs( 'u2f-frontend.js' ),
					]
				]
			];
		}

		return $mod->renderTemplate( '/pages/login_intent/index.twig',
			Services::DataManipulation()->mergeArraysRecursive(
				$mod->getUIHandler()->getBaseDisplayData(), $data ), true );
	}
}
