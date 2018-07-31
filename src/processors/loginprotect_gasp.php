<?php

if ( class_exists( 'ICWP_WPSF_Processor_LoginProtect_Gasp', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/loginprotect_base.php' );

class ICWP_WPSF_Processor_LoginProtect_Gasp extends ICWP_WPSF_Processor_LoginProtect_Base {

	/**
	 * @return string
	 */
	protected function buildLoginFormItems() {
		return $this->getGaspLoginHtml();
	}

	/**
	 * @return string
	 */
	private function getGaspLoginHtml() {

		$sUniqId = preg_replace( '#[^a-zA-Z0-9]#', '', apply_filters( 'icwp_shield_lp_gasp_uniqid', uniqid() ) );
		return $this->getFeature()->renderTemplate(
			'snippets/gasp_js.php',
			array(
				'sCbName'   => $this->getGaspCheckboxName(),
				'sLabel'    => $this->getTextImAHuman(),
				'sAlert'    => $this->getTextPleaseCheckBox(),
				'sMustJs'   => _wpsf__( 'You MUST enable Javascript to be able to login' ),
				'sUniqId'   => $sUniqId,
				'sUniqElem' => 'icwp_wpsf_login_p'.$sUniqId
			)
		);
	}

	/**
	 * @return string
	 */
	protected function getGaspCheckboxName() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();
		return $oFO->prefix( $oFO->getGaspKey() );
	}

	/**
	 * @throws Exception
	 */
	protected function performCheckWithException() {
		$oDp = $this->loadDP();
		$sGaspCheckBox = $oDp->post( $this->getGaspCheckboxName() );
		$sHoney = $oDp->post( 'icwp_wpsf_login_email' );

		$sUsername = $this->getUserToAudit();
		$sActionAttempted = $this->getActionToAudit();

		$bValid = false;
		$sError = '';
		if ( empty( $sGaspCheckBox ) ) {
			$sAuditMessage = sprintf(
								 _wpsf__( 'User "%s" attempted to %s but GASP checkbox was not present.' ),
								 $sUsername, $sActionAttempted
							 ).' '._wpsf__( 'Probably a BOT.' );
			$this->addToAuditEntry( $sAuditMessage, 3, $sActionAttempted.'_protect_block_gasp_checkbox' );
			$this->setLoginAsFailed( $sActionAttempted.'.gasp.checkbox.fail' );
			$sError = _wpsf__( "You must check that box to say you're not a bot." );
		}
		else if ( !empty( $sHoney ) ) {
			$sAuditMessage = sprintf(
								 _wpsf__( 'User "%s" attempted to %s but they were caught by the GASP honeypot.' ),
								 $sUsername, $sActionAttempted
							 ).' '._wpsf__( 'Probably a BOT.' );
			$this->addToAuditEntry( $sAuditMessage, 3, $sActionAttempted.'_protect_block_gasp_honeypot' );
			$this->setLoginAsFailed( $sActionAttempted.'.gasp.honeypot.fail' );
			$sError = sprintf( _wpsf__( 'You appear to be a bot - terminating %s attempt.' ), $sActionAttempted );
		}
		else {
			$bValid = true;
		}

		if ( !$bValid ) {
			/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
			$oFO = $this->getFeature();
			$oFO->setOptInsightsAt( sprintf( 'last_%s_block_at', $sActionAttempted ) );
			$this->setIpTransgressed(); // We now black mark this IP
			throw new Exception( $sError );
		}
	}

	/**
	 * @param string $sUsername
	 * @param string $sActionAttempted - one of 'login', 'register', 'reset-password'
	 * @return bool - true if validation successful
	 * @throws Exception
	 */
	protected function doGaspChecks( $sUsername, $sActionAttempted = 'login' ) {
		$oDp = $this->loadDP();
		$sGaspCheckBox = $oDp->post( $this->getGaspCheckboxName() );
		$sHoney = $oDp->post( 'icwp_wpsf_login_email' );

		$bValid = false;
		$sDieMessage = '';
		if ( empty( $sGaspCheckBox ) ) {
			$sAuditMessage = sprintf(
								 _wpsf__( 'User "%s" attempted to %s but GASP checkbox was not present.' ),
								 empty( $sUsername ) ? 'unknown' : $sUsername, $sActionAttempted
							 ).' '._wpsf__( 'Probably a BOT.' );
			$this->addToAuditEntry( $sAuditMessage, 3, $sActionAttempted.'_protect_block_gasp_checkbox' );
			$this->doStatIncrement( $sActionAttempted.'.gasp.checkbox.fail' );
			$sDieMessage = _wpsf__( "You must check that box to say you're not a bot." );
		}
		else if ( !empty( $sHoney ) ) {
			$sAuditMessage = sprintf(
								 _wpsf__( 'User "%s" attempted to %s but they were caught by the GASP honeypot.' ),
								 empty( $sUsername ) ? 'unknown' : $sUsername, $sActionAttempted
							 ).' '._wpsf__( 'Probably a BOT.' );
			$this->addToAuditEntry( $sAuditMessage, 3, $sActionAttempted.'_protect_block_gasp_honeypot' );
			$this->doStatIncrement( $sActionAttempted.'.gasp.honeypot.fail' );
			$sDieMessage = sprintf( _wpsf__( 'You appear to be a bot - terminating %s attempt.' ), $sActionAttempted );
		}
		else {
			$bValid = true;
		}

		if ( !$bValid ) {
			/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
			$oFO = $this->getFeature();
			$oFO->setOptInsightsAt( sprintf( 'last_%s_block_at', $sActionAttempted ) );
			$this->setIpTransgressed(); // We now black mark this IP
			throw new Exception( $sDieMessage );
		}

		return $bValid;
	}

	/**
	 * @return string
	 */
	protected function getTextImAHuman() {
		return $this->getFeature()->getTextOpt( 'text_imahuman' );
	}

	/**
	 * @return string
	 */
	protected function getTextPleaseCheckBox() {
		return $this->getFeature()->getTextOpt( 'text_pleasecheckbox' );
	}
}