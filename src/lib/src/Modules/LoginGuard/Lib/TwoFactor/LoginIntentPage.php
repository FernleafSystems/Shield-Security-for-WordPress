<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;
use FernleafSystems\Wordpress\Services\Services;

class LoginIntentPage {

	use MfaControllerConsumer;

	/**
	 */
	public function loadPage() {
		echo $this->renderPage();
	}

	/**
	 * @return string
	 */
	public function renderForm() {
		$oIC = $this->getMfaCon();
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $oIC->getMod();
		/** @var LoginGuard\Options $oOpts */
		$oOpts = $oIC->getOptions();
		$oCon = $oIC->getCon();
		$oReq = Services::Request();
		$oWP = Services::WpGeneral();

		$oNotice = $oCon->getAdminNotices()->getFlashNotice();
		if ( $oNotice instanceof NoticeVO ) {
			$sMessage = $oNotice->render_data[ 'message' ];
		}
		else {
			$sMessage = $oOpts->isChainedAuth() ?
				__( 'Please supply all authentication codes', 'wp-simple-firewall' )
				: __( 'Please supply at least 1 authentication code', 'wp-simple-firewall' );
		}

		$sReferUrl = $oReq->server( 'HTTP_REFERER', '' );
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
			$sRedirectTo = rawurlencode( $oReq->post( 'redirect_to', $oReq->getUri() ) );
		}

		$sCancelHref = $oReq->post( 'cancel_href', '' );
		if ( empty( $sCancelHref ) && Services::Data()->isValidWebUrl( $sReferUrl ) ) {
			$sCancelHref = parse_url( $sReferUrl, PHP_URL_PATH );
		}

		$nMfaSkip = (int)( $oOpts->getMfaSkip()/DAY_IN_SECONDS );
		$nTimeRemaining = $oMod->getSession()->login_intent_expires_at - $oReq->ts();
		$aDisplayData = [
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
				'login_intent_flag' => $oMod->getLoginIntentRequestFlag(),
			],
			'hrefs'   => [
				'form_action' => parse_url( $oWP->getAdminUrl( '', true ), PHP_URL_PATH ),
				'redirect_to' => $sRedirectTo,
				'cancel_href' => $sCancelHref
			],
			'flags'   => [
				'can_skip_mfa'       => $oOpts->isMfaSkip(),
				'show_branded_links' => !$oMod->isWlEnabled(), // white label mitigation
			]
		];

		return $oMod->renderTemplate( '/snippets/login_intent/form.twig',
			Services::DataManipulation()->mergeArraysRecursive( $oMod->getBaseDisplayData(), $aDisplayData ), true );
	}

	/**
	 * @return string
	 */
	private function renderPage() {
		$oIC = $this->getMfaCon();
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $oIC->getMod();
		$oCon = $oIC->getCon();
		$oReq = Services::Request();

		$aLabels = $oCon->getLabels();
		$sBannerUrl = empty( $aLabels[ 'url_login2fa_logourl' ] ) ? $oCon->getPluginUrl_Image( 'pluginlogo_banner-772x250.png' ) : $aLabels[ 'url_login2fa_logourl' ];
		$nTimeRemaining = $oMod->getSession()->login_intent_expires_at - $oReq->ts();
		$aDisplayData = [
			'strings' => [
				'what_is_this' => __( 'What is this?', 'wp-simple-firewall' ),
				'page_title'   => sprintf( __( '%s Login Verification', 'wp-simple-firewall' ), $oCon->getHumanName() ),
			],
			'data'    => [
				'time_remaining' => $nTimeRemaining,
			],
			'hrefs'   => [
				'css_bootstrap' => $oCon->getPluginUrl_Css( 'bootstrap4.min' ),
				'js_bootstrap'  => $oCon->getPluginUrl_Js( 'bootstrap4.min' ),
				'shield_logo'   => 'https://ps.w.org/wp-simple-firewall/assets/banner-772x250.png',
				'what_is_this'  => 'https://icontrolwp.freshdesk.com/support/solutions/articles/3000064840',
			],
			'imgs'    => [
				'banner'  => $sBannerUrl,
				'favicon' => $oCon->getPluginUrl_Image( 'pluginlogo_24x24.png' ),
			],
			'flags'   => [
				'show_branded_links' => !$oMod->isWlEnabled(), // white label mitigation
				'has_u2f'            => isset( $oIC->getProvidersForUser(
						Services::WpUsers()->getCurrentWpUser(), true )[ LoginGuard\Lib\TwoFactor\Provider\U2F::SLUG ] )
			],
			'content' => [
				'form' => $this->renderForm(),
			]
		];

		// Provide the U2F scripts if required.
		if ( $aDisplayData[ 'flags' ][ 'has_u2f' ] ) {
			$aDisplayData[ 'head' ] = [
				'scripts' => [
					[
						'src' => $oCon->getPluginUrl_Js( 'u2f-bundle.js' ),
					],
					[
						'src' => $oCon->getPluginUrl_Js( 'u2f-frontend.js' ),
					]
				]
			];
		}

		return $oMod->renderTemplate( '/pages/login_intent/index.twig',
			Services::DataManipulation()->mergeArraysRecursive( $oMod->getBaseDisplayData(), $aDisplayData ), true );
	}
}
