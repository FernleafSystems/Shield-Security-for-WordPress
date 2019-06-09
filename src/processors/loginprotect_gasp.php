<?php

use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_LoginProtect_Gasp extends ICWP_WPSF_Processor_LoginProtect_Base {

	/**
	 * @return string
	 */
	protected function buildFormItems() {
		return $this->getGaspLoginHtml();
	}

	/**
	 * @return string
	 */
	private function getGaspLoginHtml() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();
		$sUniqId = preg_replace( '#[^a-zA-Z0-9]#', '', apply_filters( 'icwp_shield_lp_gasp_uniqid', uniqid() ) );
		return $this->getMod()->renderTemplate(
			'snippets/gasp_js.php',
			[
				'sCbName'   => $oFO->getGaspKey(),
				'sLabel'    => $oFO->getTextImAHuman(),
				'sAlert'    => $oFO->getTextPleaseCheckBox(),
				'sMustJs'   => __( 'You MUST enable Javascript to be able to login', 'wp-simple-firewall' ),
				'sUniqId'   => $sUniqId,
				'sUniqElem' => 'icwp_wpsf_login_p'.$sUniqId,
				'strings'   => [
					'loading' => __( 'Loading', 'wp-simple-firewall' )
				]
			]
		);
	}

	/**
	 * @throws \Exception
	 */
	protected function performCheckWithException() {
		if ( $this->isFactorTested() ) {
			return;
		}
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();
		$this->setFactorTested( true );

		$oReq = Services::Request();
		$sGaspCheckBox = $oReq->post( $oFO->getGaspKey() );
		$sHoney = $oReq->post( 'icwp_wpsf_login_email' );

		$sUsername = $this->getUserToAudit();
		$sActionAttempted = $this->getActionToAudit();

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
			$this->setLoginAsFailed( $sActionAttempted.'.gasp.checkbox.fail' );
			$sError = __( "You must check that box to say you're not a bot.", 'wp-simple-firewall' );
		}
		else if ( !empty( $sHoney ) ) {
			$this->getCon()->fireEvent(
				'honeypot_fail',
				[
					'audit' => [
						'user_login' => $sUsername,
						'action'     => $sActionAttempted,
					]
				]
			);
			$this->setLoginAsFailed( $sActionAttempted.'.gasp.honeypot.fail' );
			$sError = __( 'You appear to be a bot.', 'wp-simple-firewall' );
		}
		else {
			$bValid = true;
		}

		if ( !$bValid ) {
			throw new \Exception( $sError );
		}
	}
}