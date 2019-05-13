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
			$sAuditMessage = sprintf(
								 __( 'User "%s" attempted to %s but GASP checkbox was not present.', 'wp-simple-firewall' ),
								 $sUsername, $sActionAttempted
							 ).' '.__( 'Probably a BOT.', 'wp-simple-firewall' );
			$this->addToAuditEntry( $sAuditMessage, 3, $sActionAttempted.'_protect_block_gasp_checkbox' );
			$this->setLoginAsFailed( $sActionAttempted.'.gasp.checkbox.fail' );
			$sError = __( "You must check that box to say you're not a bot.", 'wp-simple-firewall' );
		}
		else if ( !empty( $sHoney ) ) {
			$sAuditMessage = sprintf(
								 __( 'User "%s" attempted to %s but they were caught by the GASP honeypot.', 'wp-simple-firewall' ),
								 $sUsername, $sActionAttempted
							 ).' '.__( 'Probably a BOT.', 'wp-simple-firewall' );
			$this->addToAuditEntry( $sAuditMessage, 3, $sActionAttempted.'_protect_block_gasp_honeypot' );
			$this->setLoginAsFailed( $sActionAttempted.'.gasp.honeypot.fail' );
			$sError = sprintf( __( 'You appear to be a bot - terminating %s attempt.', 'wp-simple-firewall' ), $sActionAttempted );
		}
		else {
			$bValid = true;
		}

		if ( !$bValid ) {
			/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
			$oFO = $this->getMod();
			$oFO->setOptInsightsAt( sprintf( 'last_%s_block_at', $sActionAttempted ) )
				->setIpTransgressed();
			throw new \Exception( $sError );
		}
	}

	/**
	 * @param string $sUsername
	 * @param string $sActionAttempted - one of 'login', 'register', 'reset-password'
	 * @return bool - true if validation successful
	 * @throws \Exception
	 */
	protected function doGaspChecks( $sUsername, $sActionAttempted = 'login' ) {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();
		$oReq = Services::Request();
		$sGaspCheckBox = $oReq->post( $oFO->getGaspKey() );
		$sHoney = $oReq->post( 'icwp_wpsf_login_email' );

		$bValid = false;
		$sDieMessage = '';
		if ( empty( $sGaspCheckBox ) ) {
			$sAuditMessage = sprintf(
								 __( 'User "%s" attempted to %s but GASP checkbox was not present.', 'wp-simple-firewall' ),
								 empty( $sUsername ) ? 'unknown' : $sUsername, $sActionAttempted
							 ).' '.__( 'Probably a BOT.', 'wp-simple-firewall' );
			$this->addToAuditEntry( $sAuditMessage, 3, $sActionAttempted.'_protect_block_gasp_checkbox' );
			$this->doStatIncrement( $sActionAttempted.'.gasp.checkbox.fail' );
			$sDieMessage = __( "You must check that box to say you're not a bot.", 'wp-simple-firewall' );
		}
		else if ( !empty( $sHoney ) ) {
			$sAuditMessage = sprintf(
								 __( 'User "%s" attempted to %s but they were caught by the GASP honeypot.', 'wp-simple-firewall' ),
								 empty( $sUsername ) ? 'unknown' : $sUsername, $sActionAttempted
							 ).' '.__( 'Probably a BOT.', 'wp-simple-firewall' );
			$this->addToAuditEntry( $sAuditMessage, 3, $sActionAttempted.'_protect_block_gasp_honeypot' );
			$this->doStatIncrement( $sActionAttempted.'.gasp.honeypot.fail' );
			$sDieMessage = sprintf( __( 'You appear to be a bot - terminating %s attempt.', 'wp-simple-firewall' ), $sActionAttempted );
		}
		else {
			$bValid = true;
		}

		if ( !$bValid ) {
			/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
			$oFO = $this->getMod();
			$oFO->setOptInsightsAt( sprintf( 'last_%s_block_at', $sActionAttempted ) )
				->setIpTransgressed(); // We now black mark this IP
			throw new \Exception( $sDieMessage );
		}

		return $bValid;
	}
}