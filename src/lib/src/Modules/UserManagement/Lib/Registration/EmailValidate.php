<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Registration;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Verify\Email;

class EmailValidate {

	use ModConsumer;

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
	private function validateNewUserEmail( $aUserData ) {
		$sEmail = $aUserData[ 'user_email' ];
		/** @var UserManagement\Options $oOpts */
		$oOpts = $this->getOptions();

		$sInvalidBecause = null;
		if ( !is_email( $sEmail ) ) {
			$sInvalidBecause = 'syntax';
		}
		else {
			$aChecks = $oOpts->getEmailValidationChecks();
			foreach ( ( new Email() )->getEmailVerification( $sEmail ) as $sValidation => $bIsValid ) {
				if ( !$bIsValid && in_array( $sValidation, $aChecks ) ) {
					$sInvalidBecause = $sValidation;
					break;
				}
			}
		}

		if ( !empty( $sInvalidBecause ) ) {
			$this->getCon()->fireEvent(
				'reg_email_invalid',
				[
					'audit' => [
						'email'  => sanitize_email( $sEmail ),
						'reason' => sanitize_key( $sInvalidBecause ),
					]
				]
			);
			if ( $oOpts->getValidateEmailOnRegistration() == 'kill' ) {
				wp_die( 'Attempted user registration with invalid email addressed has been blocked.' );
			}
		}

		return $aUserData;
	}
}