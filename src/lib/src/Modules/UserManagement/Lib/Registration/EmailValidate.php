<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Registration;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Verify\Email;

class EmailValidate {

	use ModConsumer;

	private $aTrack;

	public function run() {
		/** @var UserManagement\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->getValidateEmailOnRegistration() != 'disabled' ) {
			add_filter( 'wp_pre_insert_user_data', [ $this, 'validateNewUserEmail' ] );
		}
	}

	/**
	 * @param array $aUserData
	 * @return array
	 */
	public function validateNewUserEmail( $aUserData ) {
		$sEmail = $aUserData[ 'user_email' ];
		/** @var UserManagement\Options $oOpts */

		if ( !is_array( $this->aTrack ) ) {
			$this->aTrack = [];
		}

		// This hook seems to be called twice on any given registration.
		if ( !in_array( $sEmail, $this->aTrack ) ) {
			$this->aTrack[] = $sEmail;

			$oOpts = $this->getOptions();
			$sInvalidBecause = null;
			if ( !is_email( $sEmail ) ) {
				$sInvalidBecause = 'syntax';
			}
			else {
				$sApiToken = $this->getCon()
								  ->getModule_License()
								  ->getWpHashesTokenManager()
								  ->getToken();
				if ( !empty( $sApiToken ) ) {
					$aChecks = $oOpts->getEmailValidationChecks();
					foreach ( ( new Email( $sApiToken ) )->getEmailVerification( $sEmail ) as $sValidation => $bIsValid ) {
						if ( !$bIsValid && in_array( $sValidation, $aChecks ) ) {
							$sInvalidBecause = $sValidation;
							break;
						}
					}
				}
			}

			if ( !empty( $sInvalidBecause ) ) {
				$sOpt = $oOpts->getValidateEmailOnRegistration();
				$this->getCon()->fireEvent(
					'reg_email_invalid',
					[
						'audit'         => [
							'email'  => sanitize_email( $sEmail ),
							'reason' => sanitize_key( $sInvalidBecause ),
						],
						'offense_count' => $sOpt == 'log' ? 0 : 1,
						'block'         => $sOpt == 'block',
					]
				);

				if ( $sOpt == 'block' ) {
					wp_die( 'Attempted user registration with invalid email addressed has been blocked.' );
				}
			}
		}

		return $aUserData;
	}
}