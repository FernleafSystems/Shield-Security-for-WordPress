<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Registration;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Verify\Email;

class EmailValidate {

	use ModConsumer;

	private $track;

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
		$email = $aUserData[ 'user_email' ];
		/** @var UserManagement\Options $opts */

		if ( !is_array( $this->track ) ) {
			$this->track = [];
		}

		// This hook seems to be called twice on any given registration.
		if ( !empty( $email ) && !in_array( $email, $this->track ) ) {
			$this->track[] = $email;

			$opts = $this->getOptions();
			$sInvalidBecause = null;
			if ( !is_email( $email ) ) {
				$sInvalidBecause = 'syntax';
			}
			else {
				$apiToken = $this->getCon()
								 ->getModule_License()
								 ->getWpHashesTokenManager()
								 ->getToken();
				if ( !empty( $apiToken ) ) {
					$aChecks = $opts->getEmailValidationChecks();
					$aVerifys = ( new Email( $apiToken ) )->getEmailVerification( $email );
					if ( is_array( $aVerifys ) ) {
						foreach ( $aVerifys as $sVerifyKey => $bIsValid ) {
							if ( !$bIsValid && in_array( $sVerifyKey, $aChecks ) ) {
								$sInvalidBecause = $sVerifyKey;
								break;
							}
						}
					}
				}
			}

			if ( !empty( $sInvalidBecause ) ) {
				$opt = $opts->getValidateEmailOnRegistration();
				$this->getCon()->fireEvent(
					'reg_email_invalid',
					[
						'audit'         => [
							'email'  => sanitize_email( $email ),
							'reason' => sanitize_key( $sInvalidBecause ),
						],
						'offense_count' => $opt == 'log' ? 0 : 1,
						'block'         => $opt == 'block',
					]
				);

				if ( $opt == 'block' ) {
					wp_die( 'Attempted user registration with invalid email address has been blocked.' );
				}
			}
		}

		return $aUserData;
	}
}