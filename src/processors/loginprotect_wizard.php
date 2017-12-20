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

		$this->loadAutoload(); // for Response
		switch ( $this->loadDP()->post( 'wizard-step' ) ) {

			case 'authemail':
				$oResponse = $this->processAuthEmail();
				break;

			case 'authga':
				$oResponse = $this->processAuthGa();
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

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function processAuthEmail() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();
		$oDP = $this->loadDP();

		$oResponse = new \FernleafSystems\Utilities\Response();
		$oResponse->setSuccessful( false );

		$sEmail = $oDP->post( 'email' );
		$sCode = $oDP->post( 'code' );
		$bFa = $oDP->post( 'Email2FAOption' ) === 'Y';

		if ( !$oDP->validEmail( $sEmail ) ) {
			$sMessage = _wpsf__( 'Invalid email address' );
		}
		else {
			if ( empty( $sCode ) ) {
				if ( $oFO->sendEmailVerifyCanSend( $sEmail, false ) ) {
					$oResponse->setSuccessful( true );
					$sMessage = 'Verification email sent - please check your email (including your SPAM)';
				}
				else {
					$sMessage = 'Failed to send verification email';
				}
			}
			else {
				if ( $sCode == $oFO->getCanEmailVerifyCode() ) {
					$oResponse->setSuccessful( true );
					$sMessage = 'Email sending has been verified successfully.';

					$oFO->setIfCanSendEmail( true );

					if ( $bFa ) {
						$oFO->setEnabled2FaEmail( true );
						$sMessage .= ' '.'Email-based two factor authentication is now enabled.';
					}
					else {
						$sMessage .= ' '.'Email-based two factor authentication is NOT enabled.';
					}
				}
				else {
					$sMessage = 'This does not appear to be the correct 6-digit code that was sent to you.'
								.'Email-based two factor authentication option has not been updated.';
				}
			}
		}

		return $oResponse->setMessageText( $sMessage );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function processAuthGa() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();
		$oDP = $this->loadDP();

		$oResponse = new \FernleafSystems\Utilities\Response();
		$oResponse->setSuccessful( false );

		$sCode = $oDP->post( 'code' );

		if ( empty( $sCode ) ) {
			$sMessage = _wpsf__( 'Code was empty.' );
		}
		else {

			$oUser = $this->loadWpUsers()->getCurrentWpUser();
			/** @var ICWP_WPSF_Processor_LoginProtect $oProc */
			$oProc = $oFO->getProcessor();
			$oProcGa = $oProc->getProcessorLoginIntent()
							 ->getProcessorGoogleAuthenticator();
			$bValidated = $oProcGa->validateGaCode( $oUser, $sCode );

			if ( $bValidated ) {
				$oFO->setEnabled2FaGoogleAuthenticator( true );
				$oProcGa->setProfileValidated( $oUser, true );

				$oResponse->setSuccessful( true );
				$sMessage = 'Google Authenticator was validated.';
			}
			else {
				$sMessage = 'Could not validate - this does not appear to be the correct 6-digit code.';
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

		if ( !$oFO->getIfCanSendEmailVerified() || !$oFO->getIsEmailAuthenticationEnabled() ) {
			$aStepsSlugs[] = 'mfa_authemail';
		}

		if ( !$oFO->getIsEnabledGoogleAuthenticator() ) {
			$aStepsSlugs[] = 'mfa_authga';
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

			case 'mfa_authemail':
				$oUser = $this->loadWpUsers()->getCurrentWpUser();
				$aAdd = array(
					'data' => array(
						'name'       => $oUser->first_name,
						'user_email' => $oUser->user_email
					)
				);
				break;

			case 'mfa_authga':
				$oUser = $this->loadWpUsers()->getCurrentWpUser();
				/** @var ICWP_WPSF_Processor_LoginProtect $oProc */
				$oProc = $oFO->getProcessor();
				$sGaUrl = $oProc->getProcessorLoginIntent()
								->getProcessorGoogleAuthenticator()
								->getGaRegisterChartUrl( $oUser );
				$aAdd = array(
					'data'  => array(
						'name'       => $oUser->first_name,
						'user_email' => $oUser->user_email
					),
					'hrefs' => array(
						'ga_chart' => $sGaUrl,
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
			'mfa_start'     => array(
				'title'             => _wpsf__( 'Start Multi-Factor Authentication Setup' ),
				'restricted_access' => false
			),
			'mfa_authemail' => array(
				'title' => _wpsf__( 'Email Authentication' ),
			),
			'mfa_authga'    => array(
				'title' => _wpsf__( 'Google Authenticator' ),
			),
			'mfa_finished'  => array(
				'title'             => _wpsf__( 'Finished: Multi-Factor Authentication Setup' ),
				'restricted_access' => false
			),
		);
	}
}