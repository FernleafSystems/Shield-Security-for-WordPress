<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;
use FernleafSystems\Wordpress\Services\Services;

class LoginIntentPage {

	use Shield\Modules\ModConsumer;

	/**
	 *
	 * @param MfaLoginController $oIC
	 * @return bool - true if valid form printed, false otherwise. Should die() if true
	 */
	public function run( MfaLoginController $oIC ) {
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();
		/** @var LoginGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		$oCon = $this->getCon();
		$oReq = Services::Request();
		$oWP = Services::WpGeneral();

		$aLoginIntentFields = array_map(
			function ( $oProvider ) {
				/** @var TwoFactor\Provider\BaseProvider $oProvider */
				return $oProvider->getFormField();
			},
			$oIC->getProvidersForUser( Services::WpUsers()->getCurrentWpUser() )
		);

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
			$sCancelHref = rawurlencode( parse_url( $sReferUrl, PHP_URL_PATH ) );
		}

		$aLabels = $oCon->getLabels();
		$sBannerUrl = empty( $aLabels[ 'url_login2fa_logourl' ] ) ? $oCon->getPluginUrl_Image( 'pluginlogo_banner-772x250.png' ) : $aLabels[ 'url_login2fa_logourl' ];
		$nMfaSkip = $oOpts->getMfaSkip();
		$nTimeRemaining = $oMod->getSession()->login_intent_expires_at - $oReq->ts();
		$aDisplayData = [
			'strings' => [
				'cancel'          => __( 'Cancel Login', 'wp-simple-firewall' ),
				'time_remaining'  => __( 'Time Remaining', 'wp-simple-firewall' ),
				'calculating'     => __( 'Calculating', 'wp-simple-firewall' ).' ...',
				'seconds'         => strtolower( __( 'Seconds', 'wp-simple-firewall' ) ),
				'login_expired'   => __( 'Login Expired', 'wp-simple-firewall' ),
				'verify_my_login' => __( 'Verify My Login', 'wp-simple-firewall' ),
				'more_info'       => __( 'More Info', 'wp-simple-firewall' ),
				'what_is_this'    => __( 'What is this?', 'wp-simple-firewall' ),
				'message'         => $sMessage,
				'page_title'      => sprintf( __( '%s Login Verification', 'wp-simple-firewall' ), $oCon->getHumanName() ),
				'skip_mfa'        => sprintf(
					__( "Don't ask again on this browser for %s.", 'wp-simple-firewall' ),
					sprintf( _n( '%s day', '%s days', $nMfaSkip, 'wp-simple-firewall' ), $nMfaSkip )
				)
			],
			'data'    => [
				'login_fields'      => $aLoginIntentFields,
				'time_remaining'    => $nTimeRemaining,
				'message_type'      => 'info',
				'login_intent_flag' => $oMod->getLoginIntentRequestFlag(),
				'page_locale'       => $oWP->getLocale( '-' )
			],
			'hrefs'   => [
				'form_action'   => parse_url( $oWP->getAdminUrl( '', true ), PHP_URL_PATH ),
				'css_bootstrap' => $oCon->getPluginUrl_Css( 'bootstrap4.min' ),
				'js_bootstrap'  => $oCon->getPluginUrl_Js( 'bootstrap4.min' ),
				'shield_logo'   => 'https://ps.w.org/wp-simple-firewall/assets/banner-772x250.png',
				'redirect_to'   => $sRedirectTo,
				'what_is_this'  => 'https://icontrolwp.freshdesk.com/support/solutions/articles/3000064840',
				'cancel_href'   => $sCancelHref
			],
			'imgs'    => [
				'banner'  => $sBannerUrl,
				'favicon' => $oCon->getPluginUrl_Image( 'pluginlogo_24x24.png' ),
			],
			'flags'   => [
				'can_skip_mfa'       => $oMod->getMfaSkipEnabled(),
				'show_branded_links' => !$oMod->isWlEnabled(), // white label mitigation
			]
		];

		echo $oMod->renderTemplate( '/pages/login_intent/index.twig',
			Services::DataManipulation()->mergeArraysRecursive( $oMod->getBaseDisplayData(), $aDisplayData ), true );

		return true;
	}
}
