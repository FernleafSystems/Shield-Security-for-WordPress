<?php

/**
 * Class ICWP_WPSF_Processor_LoginProtect_Wizard
 */
class ICWP_WPSF_Wizard_LoginProtect extends ICWP_WPSF_Wizard_BaseWpsf {

	/**
	 * @return string
	 */
	protected function getPageTitle() {
		return sprintf( _wpsf__( '%s Multi-Factor Authentication Wizard' ), $this->getPluginCon()->getHumanName() );
	}

	/**
	 * @param string $sStep
	 * @return \FernleafSystems\Utilities\Response|null
	 */
	protected function processWizardStep( $sStep ) {
		switch ( $sStep ) {
			case 'authemail':
				$oResponse = $this->processAuthEmail();
				break;

			case 'authga':
				$oResponse = $this->processAuthGa();
				break;

			case 'multiselect':
				$oResponse = $this->processMultiSelect();
				break;

			default:
				$oResponse = parent::processWizardStep( $sStep );
				break;
		}
		return $oResponse;
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function processAuthEmail() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getModCon();
		$oReq = $this->loadRequest();

		$oResponse = new \FernleafSystems\Utilities\Response();
		$oResponse->setSuccessful( false );

		$sEmail = $oReq->post( 'email' );
		$sCode = $oReq->post( 'code' );
		$bFa = $oReq->post( 'Email2FAOption' ) === 'Y';

		if ( !$this->loadDP()->validEmail( $sEmail ) ) {
			$sMessage = _wpsf__( 'Invalid email address' );
		}
		else {
			if ( empty( $sCode ) ) {
				if ( $oFO->sendEmailVerifyCanSend( $sEmail, false ) ) {
					$oFO->setIfCanSendEmail( false );
					$oResponse->setSuccessful( true );
					$sMessage = _wpsf__( 'Verification email sent (please check your email including your SPAM).' )
								.' '._wpsf__( 'Enter the code from the email into the form above and click the button to verify.' );
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
		$oFO = $this->getModCon();
		$oReq = $this->loadRequest();

		$oResponse = new \FernleafSystems\Utilities\Response();
		$oResponse->setSuccessful( false );

		$sCode = $oReq->post( 'gacode' );
		$bEnableGa = $oReq->post( 'enablega' ) === 'Y';

		$sMessage = '';
		if ( $sCode != 'ignore' ) {

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
					$oProcGa->setProfileValidated( $oUser, true );
					$sMessage = 'Google Authenticator was validated.';
					$oResponse->setSuccessful( true );
				}
				else {
					$sMessage = 'Could not validate - this does not appear to be the correct 6-digit code.';
					$bEnableGa = false; // we don't enable GA on the site if the code was bad.
				}
			}
		}
		else {
			$oResponse->setSuccessful( true );
		}

		if ( $bEnableGa ) {
			$oFO->setEnabled2FaGoogleAuthenticator( true );
			$sMessage .= ' '._wpsf__( 'Google Authenticator was enabled for the site.' );
		}

		return $oResponse->setMessageText( $sMessage );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function processMultiSelect() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getModCon();

		$bEnabledMulti = $this->loadRequest()->post( 'multiselect' ) === 'Y';
		$oFO->setIsChainedAuth( $bEnabledMulti );
		$sMessage = sprintf( _wpsf__( 'Multi-Factor Authentication was %s for the site.' ),
			$bEnabledMulti ? _wpsf__( 'enabled' ) : _wpsf__( 'disabled' )
		);

		return ( new \FernleafSystems\Utilities\Response() )
			->setSuccessful( true )
			->setMessageText( $sMessage );
	}

	/**
	 * @return string[]
	 * @throws Exception
	 */
	protected function determineWizardSteps() {

		switch ( $this->getWizardSlug() ) {
			case 'mfa':
				$aSteps = $this->determineWizardSteps_Mfa();
				break;
			default:
				parent::determineWizardSteps();
				break;
		}
		return array_values( array_intersect( array_keys( $this->getAllDefinedSteps() ), $aSteps ) );
	}

	/**
	 * @return string[]
	 */
	private function determineWizardSteps_Mfa() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getModCon();

		$aStepsSlugs = array( 'start' );

		if ( !$oFO->getIfCanSendEmailVerified() || !$oFO->isEmailAuthenticationActive() ) {
			$aStepsSlugs[] = 'authemail';
		}

		if ( !$oFO->isEnabledGoogleAuthenticator() ) {
			$aStepsSlugs[] = 'authga';
		}

		$aStepsSlugs[] = 'multiselect';
		$aStepsSlugs[] = 'finished';
		return $aStepsSlugs;
	}

	/**
	 * @param string $sStep
	 * @return array
	 */
	protected function getRenderData_SlideExtra( $sStep ) {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getModCon();

		$aAdditional = array();

		switch ( $sStep ) {

			case 'authemail':
				$oUser = $this->loadWpUsers()->getCurrentWpUser();
				$aAdditional = array(
					'data' => array(
						'name'       => $oUser->first_name,
						'user_email' => $oUser->user_email
					)
				);
				break;

			case 'authga':
				$oUser = $this->loadWpUsers()->getCurrentWpUser();
				/** @var ICWP_WPSF_Processor_LoginProtect $oProc */
				$oProc = $oFO->getProcessor();
				$oProcGa = $oProc->getProcessorLoginIntent()
								 ->getProcessorGoogleAuthenticator();
				$sGaUrl = $oProcGa->getGaRegisterChartUrl( $oUser );
				$aAdditional = array(
					'data'  => array(
						'name'       => $oUser->first_name,
						'user_email' => $oUser->user_email
					),
					'hrefs' => array(
						'ga_chart' => $sGaUrl,
					),
					'flags' => array(
						'has_ga' => $oProcGa->getCurrentUserHasValidatedProfile(),
					)
				);
				break;

			case 'multiselect':
				$aAdditional = array(
					'flags' => array(
						'has_multiselect' => $oFO->isChainedAuth(),
					)
				);
				break;

			default:
				break;
		}

		if ( empty( $aAdditional ) ) {
			$aAdditional = parent::getRenderData_SlideExtra( $sStep );
		}
		return $aAdditional;
	}
}