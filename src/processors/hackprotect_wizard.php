<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect_Wizard', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wizard.php' );

/**
 * Class ICWP_WPSF_Processor_LoginProtect_Wizard
 */
class ICWP_WPSF_Processor_HackProtect_Wizard extends ICWP_WPSF_Processor_Base_Wizard {

	/**
	 * @return string[]
	 */
	protected function getSupportedWizards() {
		return array( 'ccs', 'fcs', 'wpvuln' );
	}

	/**
	 * @return string
	 */
	protected function getPageTitle() {
		return sprintf( _wpsf__( '%s Hack Protect Wizard' ), $this->getController()->getHumanName() );
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
				$oResponse = null; // we don't process any steps we don't recognise.
				break;
		}
		return $oResponse;
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
		$bEnableGa = $oDP->post( 'enablega' ) === 'Y';

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
		$oFO = $this->getFeature();

		$bEnabledMulti = $this->loadDP()->post( 'multiselect' ) === 'Y';
		$oFO->setIsChainedAuth( $bEnabledMulti );
		$sMessage = sprintf( _wpsf__( 'Multi-Factor Authentication was %s for the site.' ),
			$bEnabledMulti ? _wpsf__( 'enabled' ) : _wpsf__( 'disabled' )
		);

		$oResponse = new \FernleafSystems\Utilities\Response();
		return $oResponse->setSuccessful( true )
						 ->setMessageText( $sMessage );
	}

	/**
	 * @return string[]
	 */
	protected function determineWizardSteps() {

		switch ( $this->getCurrentWizard() ) {
			case 'ccs':
				$aSteps = $this->determineWizardSteps_Ccs();
				break;
			case 'fcs':
				$aSteps = $this->determineWizardSteps_Fcs();
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
	private function determineWizardSteps_Ccs() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();

		$aStepsSlugs = array(
			'ccs_start',
			'ccs_scanresult',
			'ccs_finished'
		);
		return $aStepsSlugs;
	}

	/**
	 * @return string[]
	 */
	private function determineWizardSteps_Fcs() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();

		$aStepsSlugs = array(
			'fcs_start',
			'fcs_scanresult',
			'fcs_finished'
		);
		return $aStepsSlugs;
	}

	/**
	 * @param string $sSlug
	 * @return array
	 */
	protected function getRenderDataForStep( $sSlug ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();
		/** @var ICWP_WPSF_Processor_HackProtect $oProc */
		$oProc = $oFO->getProcessor();

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

			case 'fcs_scanresult':
				$oProc = $oProc->getSubProcessorFileCleanerScan();
				$aAdd = array(
					'data' => array(
						'file_list'  => $oProc->discoverFiles(),
					)
				);
				break;

			case 'mfa_authga':
				$oUser = $this->loadWpUsers()->getCurrentWpUser();
				/** @var ICWP_WPSF_Processor_LoginProtect $oProc */
				$oProc = $oFO->getProcessor();
				$oProcGa = $oProc->getProcessorLoginIntent()
								 ->getProcessorGoogleAuthenticator();
				$sGaUrl = $oProcGa->getGaRegisterChartUrl( $oUser );
				$aAdd = array(
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

			case 'mfa_multiselect':
				$aAdd = array(
					'flags' => array(
						'has_multiselect' => $oFO->isChainedAuth(),
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
			'fcs_start'       => array(
				'title'             => _wpsf__( 'Start File Cleaner' ),
				'restricted_access' => false
			),
			'fcs_scanresult'   => array(
				'title' => _wpsf__( 'Scan Result' ),
			),
			'fcs_finished'    => array(
				'title'             => _wpsf__( 'Finished: File Cleaner' ),
				'restricted_access' => false
			),
		);
	}
}