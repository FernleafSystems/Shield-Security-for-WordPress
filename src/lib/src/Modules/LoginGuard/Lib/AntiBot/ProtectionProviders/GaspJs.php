<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

class GaspJs extends BaseProtectionProvider {

	/**
	 * @inheritDoc
	 */
	public function performCheck( $oForm ) {
		if ( $this->isFactorTested() ) {
			return;
		}

		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();
		$this->setFactorTested( true );

		$oReq = Services::Request();
		$sGaspCheckBox = $oReq->post( $oMod->getGaspKey() );
		$sHoney = $oReq->post( 'icwp_wpsf_login_email' );

		$sUsername = $oForm->getUserToAudit();
		$sActionAttempted = $oForm->getActionToAudit();

		$bValid = false;
		$sError = '';
		if ( empty( $sGaspCheckBox ) ) {
			$this->getCon()->fireEvent(
				'botbox_fail',
				[
					'audit' => [
						'user_login' => $sUsername,
						'action'     => $sActionAttempted,
					]
				]
			);
			$sError = __( "You must check that box to say you're not a bot.", 'wp-simple-firewall' );
		}
		elseif ( !empty( $sHoney ) ) {
			$this->getCon()->fireEvent(
				'honeypot_fail',
				[
					'audit' => [
						'user_login' => $sUsername,
						'action'     => $sActionAttempted,
					]
				]
			);
			$sError = __( 'You appear to be a bot.', 'wp-simple-firewall' );
		}
		else {
			$bValid = true;
		}

		if ( !$bValid ) {
			$this->processFailure();
			throw new \Exception( $sError );
		}
	}

	public function onWpEnqueueJs() {
		$oCon = $this->getCon();
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();
		/** @var LoginGuard\Options $oOpts */
		$oOpts = $this->getOptions();

		$sAsset = 'shield-antibot';
		$sUnique = $oMod->prefix( $sAsset );
		wp_register_script(
			$sUnique,
			$oCon->getPluginUrl_Js( $sAsset ),
			[ 'jquery' ],
			$oCon->getVersion(),
			true
		);
		wp_enqueue_script( $sUnique );

		wp_localize_script(
			$sUnique,
			'icwp_wpsf_vars_lpantibot',
			[
				'form_selectors' => implode( ',', $oOpts->getAntiBotFormSelectors() ),
				'uniq'           => preg_replace( '#[^a-zA-Z0-9]#', '', apply_filters( 'icwp_shield_lp_gasp_uniqid', uniqid() ) ),
				'cbname'         => $oMod->getGaspKey(),
				'strings'        => [
					'label'   => $oMod->getTextImAHuman(),
					'alert'   => $oMod->getTextPleaseCheckBox(),
					'loading' => __( 'Loading', 'wp-simple-firewall' )
				],
				'flags'          => [
					'gasp'    => $oMod->isEnabledGaspCheck(),
					'captcha' => $oMod->isEnabledCaptcha(),
				]
			]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function buildFormInsert( $oFormProvider ) {
		return $this->getMod()->renderTemplate(
			'/snippets/anti_bot/gasp_js.twig',
			[
				'strings' => [
					'loading' => __( 'Loading', 'wp-simple-firewall' )
				]
			]
		);
	}
}