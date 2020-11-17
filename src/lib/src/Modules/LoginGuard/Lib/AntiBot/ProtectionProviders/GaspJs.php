<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

class GaspJs extends BaseProtectionProvider {

	public function setup() {
		if ( Services::Request()->query( 'wp_service_worker', 0 ) != 1 ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'onWpEnqueueJs' ] );
			add_action( 'login_enqueue_scripts', [ $this, 'onWpEnqueueJs' ] );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function performCheck( $oForm ) {
		if ( $this->isFactorTested() ) {
			return;
		}

		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		$this->setFactorTested( true );

		$req = Services::Request();
		$sGaspCheckBox = $req->post( $mod->getGaspKey() );
		$sHoney = $req->post( 'icwp_wpsf_login_email' );

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
			$sError = __( "Please check that box to say you're human, and not a bot.", 'wp-simple-firewall' );
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
		$con = $this->getCon();
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();

		$sAsset = 'shield-antibot';
		$sUnique = $con->prefix( $sAsset );
		wp_register_script(
			$sUnique,
			$con->getPluginUrl_Js( $sAsset ),
			[ 'jquery' ],
			$con->getVersion()
		);
		wp_enqueue_script( $sUnique );

		wp_localize_script(
			$sUnique,
			'icwp_wpsf_vars_lpantibot',
			[
				'form_selectors' => implode( ',', $opts->getAntiBotFormSelectors() ),
				'uniq'           => preg_replace( '#[^a-zA-Z0-9]#', '', apply_filters( 'icwp_shield_lp_gasp_uniqid', uniqid() ) ),
				'cbname'         => $mod->getGaspKey(),
				'strings'        => [
					'label'   => $mod->getTextImAHuman(),
					'alert'   => $mod->getTextPleaseCheckBox(),
					'loading' => __( 'Loading', 'wp-simple-firewall' )
				],
				'flags'          => [
					'gasp'    => $opts->isEnabledGaspCheck(),
					'captcha' => $mod->isEnabledCaptcha(),
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