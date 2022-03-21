<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Wizard_LoginProtect extends ICWP_WPSF_Wizard_BaseWpsf {

	/**
	 * @return string
	 */
	protected function getPageTitle() :string {
		return sprintf( __( '%s Multi-Factor Authentication Wizard', 'wp-simple-firewall' ), $this->getCon()
																								  ->getHumanName() );
	}

	/**
	 * @param string $step
	 * @return \FernleafSystems\Utilities\Response|null
	 */
	protected function processWizardStep( string $step ) {
		switch ( $step ) {
			case 'authemail':
				$oResponse = $this->processAuthEmail();
				break;

			default:
				$oResponse = parent::processWizardStep( $step );
				break;
		}
		return $oResponse;
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function processAuthEmail() {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		$oReq = Services::Request();

		$oResponse = new \FernleafSystems\Utilities\Response();
		$oResponse->setSuccessful( false );

		$sEmail = $oReq->post( 'email' );
		$sCode = $oReq->post( 'code' );
		$bFa = $oReq->post( 'Email2FAOption' ) === 'Y';

		if ( !Services::Data()->validEmail( $sEmail ) ) {
			$sMessage = __( 'Invalid email address', 'wp-simple-firewall' );
		}
		elseif ( empty( $sCode ) ) {
			if ( $mod->sendEmailVerifyCanSend( $sEmail, false ) ) {
				$mod->setIfCanSendEmail( false );
				$oResponse->setSuccessful( true );
				$sMessage = __( 'Verification email sent (please check your email including your SPAM).', 'wp-simple-firewall' )
							.' '.__( 'Enter the code from the email into the form above and click the button to verify.', 'wp-simple-firewall' );
			}
			else {
				$sMessage = 'Failed to send verification email';
			}
		}
		elseif ( $sCode == $mod->getCanEmailVerifyCode() ) {
			$oResponse->setSuccessful( true );
			$sMessage = 'Email sending has been verified successfully.';

			$mod->setIfCanSendEmail( true );

			if ( $bFa ) {
				$mod->setEnabled2FaEmail( true );
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

		return $oResponse->setMessageText( $sMessage );
	}

	/**
	 * @return string[]
	 * @throws Exception
	 */
	protected function determineWizardSteps() :array {

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
		/** @var LoginGuard\Options $oOpts */
		$oOpts = $this->getOptions();

		$aStepsSlugs = [ 'start' ];

		if ( !$oOpts->getIfCanSendEmailVerified() || !$oOpts->isEmailAuthenticationActive() ) {
			$aStepsSlugs[] = 'authemail';
		}

		$aStepsSlugs[] = 'finished';
		return $aStepsSlugs;
	}

	/**
	 * @param string $step
	 * @return array
	 */
	protected function getRenderData_SlideExtra( $step ) {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();

		$aAdditional = [];

		switch ( $step ) {

			case 'authemail':
				$user = Services::WpUsers()->getCurrentWpUser();
				$aAdditional = [
					'data' => [
						'name'       => $user->first_name,
						'user_email' => $user->user_email
					]
				];
				break;

			default:
				break;
		}

		if ( empty( $aAdditional ) ) {
			$aAdditional = parent::getRenderData_SlideExtra( $step );
		}
		return $aAdditional;
	}
}