<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Options;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Wizard_LoginProtect extends ICWP_WPSF_Wizard_BaseWpsf {

	protected function getPageTitle() :string {
		return sprintf( __( '%s Multi-Factor Authentication Wizard', 'wp-simple-firewall' ), $this->getCon()
																								  ->getHumanName() );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response|null
	 */
	protected function processWizardStep( string $step ) {
		switch ( $step ) {
			case 'authemail':
				$response = $this->processAuthEmail();
				break;

			default:
				$response = parent::processWizardStep( $step );
				break;
		}
		return $response;
	}

	private function processAuthEmail() :\FernleafSystems\Utilities\Response{
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();
		$req = Services::Request();

		$response = new \FernleafSystems\Utilities\Response();
		$response->setSuccessful( false );

		$email = $req->post( 'email' );
		$code = $req->post( 'code' );
		$is2FA = $req->post( 'Email2FAOption' ) === 'Y';

		if ( empty( $email ) || !Services::Data()->validEmail( $email ) ) {
			$msg = __( 'Invalid email address', 'wp-simple-firewall' );
		}
		elseif ( empty( $code ) ) {
			if ( $mod->sendEmailVerifyCanSend( $email, false ) ) {
				$mod->setIfCanSendEmail( false );
				$response->setSuccessful( true );
				$msg = __( 'Verification email sent (please check your email including your SPAM).', 'wp-simple-firewall' )
					   .' '.__( 'Enter the code from the email into the form above and click the button to verify.', 'wp-simple-firewall' );
			}
			else {
				$msg = 'Failed to send verification email';
			}
		}
		elseif ( $code == $mod->getCanEmailVerifyCode() ) {
			$response->setSuccessful( true );
			$msg = 'Email sending has been verified successfully.';

			$mod->setIfCanSendEmail( true );

			if ( $is2FA ) {
				$opts->setOpt( 'enable_email_authentication', 'Y' );
				$msg .= ' '.'Email-based two factor authentication is now enabled.';
			}
			else {
				$msg .= ' '.'Email-based two factor authentication is NOT enabled.';
			}
		}
		else {
			$msg = 'This does not appear to be the correct 6-digit code that was sent to you.'
				   .'Email-based two factor authentication option has not been updated.';
		}

		return $response->setMessageText( $msg );
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
		$additional = [];

		switch ( $step ) {

			case 'authemail':
				$user = Services::WpUsers()->getCurrentWpUser();
				$additional = [
					'data' => [
						'name'       => $user->first_name,
						'user_email' => $user->user_email
					]
				];
				break;

			default:
				break;
		}

		if ( empty( $additional ) ) {
			$additional = parent::getRenderData_SlideExtra( $step );
		}
		return $additional;
	}
}