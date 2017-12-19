<?php

if ( class_exists( 'ICWP_WPSF_Processor_Plugin_Wizard', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wizard.php' );

/**
 * Class ICWP_WPSF_Processor_LoginProtect_Wizard
 */
class ICWP_WPSF_Processor_LoginProtect_Wizard extends ICWP_WPSF_Processor_Base_Wizard {

	/**
	 * @return string[]
	 */
	protected function getSupportedWizards() {
		return array( 'mfa' );
	}

	/**
	 * @return string
	 */
	protected function getPageTitle() {
		return sprintf( _wpsf__( '%s Multi-Factor Authentication Wizard' ), $this->getController()->getHumanName() );
	}

	public function ajaxWizardProcessStepSubmit() {
		$oDP = $this->loadDP();

		$this->loadAutoload(); // for Response
		switch ( $this->loadDP()->post( 'wizard-step' ) ) {

			case 'emailcansend':
				$oResponse = $this->processEmailCanSend();
				break;

			default:
				return; // we don't process any steps we don't recognise.
				break;
		}

		$sMessage = $oResponse->getMessageText();
		if ( $oResponse->successful() ) {
			$sMessage .= '<br />'.sprintf( _wpsf__( 'Please click %s to continue.' ), _wpsf__( 'Next' ) );
		}
		else {
			$sMessage = sprintf( '%s: %s', _wpsf__( 'Error' ), $sMessage );
		}

		$aData = $oResponse->getData();
		$aData[ 'message' ] = $sMessage;

		$this->getFeature()
			 ->sendAjaxResponse( $oResponse->successful(), $aData );
	}

	private function processEmailCanSend() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();
		$oDP = $this->loadDP();

		$oResponse = new \FernleafSystems\Utilities\Response();
		$oResponse->setSuccessful( false );
		$sMessage = 'Unknown Error';

		$sEmail = $oDP->post( 'email' );
		$sCode = $oDP->post( 'code' );

		if ( !$oDP->validEmail( $sEmail ) ) {
			$sMessage = _wpsf__( 'Invalid email address' );
		}
		else {
			if ( empty( $sCode ) ) {
				if ( $oFO->sendEmailVerifyCanSend( $sEmail, false ) ) {
					$oResponse->setSuccessful( true );
					$sMessage = 'Verification email sent';
				}
				else {
					$sMessage = 'Failed to send verification email';
				}
			}
		}

		return $oResponse->setMessageText( $sMessage );
	}

	/**
	 * @return string[]
	 */
	protected function determineWizardSteps() {

		switch ( $this->getCurrentWizard() ) {
			case 'mfa':
				$aSteps = $this->determineWizardSteps_Mfa();
				break;
			default:
				$aSteps = array();
				break;
		}

		return $aSteps;
	}

	/**
	 * @return string[]
	 */
	private function determineWizardSteps_Mfa() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();

		$aStepsSlugs = array( 'mfa_start' );

		if ( !$oFO->getIfCanSendEmailVerified() ) {
			$aStepsSlugs[] = 'mfa_email_cansend';
		}

		$aStepsSlugs[] = 'mfa_finished';
		return $aStepsSlugs;
	}

	/**
	 * @param string $sSlug
	 * @return array
	 */
	protected function getRenderDataForStep( $sSlug ) {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();
		$oConn = $this->getController();

		$aData = array(
			'flags' => array(
				'is_premium' => $oFO->isPremium()
			),
			'hrefs' => array(
				'dashboard' => $oFO->getFeatureAdminPageUrl(),
				'gopro'     => 'http://icwp.io/ap',
			),
			'imgs'  => array(),
		);

		$aAdd = array();

		switch ( $sSlug ) {

			case 'mfa_email_cansend':
				$oUser = $this->loadWpUsers()->getCurrentWpUser();
				$aAdd = array(
					'data' => array(
						'name'       => $oUser->first_name,
						'user_email' => $oUser->user_email
					)
				);
				break;
			default:
				break;
		}

		return $this->loadDP()->mergeArraysRecursive( $aData, $aAdd );
	}

	/**
	 * @return array[]
	 */
	protected function getAllDefinedSteps() {
		return array(
			'mfa_start'         => array(
				'title'             => _wpsf__( 'Start Multi-Factor Authentication Setup' ),
				'restricted_access' => false
			),
			'mfa_email_cansend' => array(
				'title' => _wpsf__( 'Verify Email Sending' ),
			),
			'mfa_finished'      => array(
				'title'             => _wpsf__( 'Finished: Multi-Factor Authentication Setup' ),
				'restricted_access' => false
			),
		);
	}
}