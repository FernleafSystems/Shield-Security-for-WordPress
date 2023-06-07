<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Registration;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\ModConsumer;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Verify\Email;

class EmailValidate {

	use ExecOnce;
	use ModConsumer;

	private $track;

	protected function run() {
		if ( $this->opts()->isValidateEmailOnRegistration() ) {
			add_filter( 'wp_pre_insert_user_data', [ $this, 'validateNewUserEmail' ] );
		}
	}

	/**
	 * @param array $userData
	 * @return array
	 */
	public function validateNewUserEmail( $userData ) {
		$opts = $this->opts();

		$email = $userData[ 'user_email' ] ?? '';

		if ( !is_array( $this->track ) ) {
			$this->track = [];
		}

		// This hook seems to be called twice on any given registration.
		if ( !empty( $email ) && !in_array( $email, $this->track ) ) {
			$this->track[] = $email;

			$invalidBecause = null;
			if ( !is_email( $email ) ) {
				$invalidBecause = 'syntax';
			}
			else {
				$apiToken = $this->con()
								 ->getModule_License()
								 ->getWpHashesTokenManager()
								 ->getToken();
				if ( !empty( $apiToken ) ) {
					$checks = $opts->getEmailValidationChecks();
					$verifys = ( new Email( $apiToken ) )->getEmailVerification( $email );
					if ( is_array( $verifys ) ) {
						foreach ( $verifys as $verifyKey => $valid ) {
							if ( !$valid && in_array( $verifyKey, $checks ) ) {
								$invalidBecause = $verifyKey;
								break;
							}
						}
					}
				}
			}

			if ( !empty( $invalidBecause ) ) {
				$opt = $opts->getValidateEmailOnRegistration();
				$this->con()->fireEvent(
					'reg_email_invalid',
					[
						'audit_params'  => [
							'email'  => sanitize_email( $email ),
							'reason' => sanitize_key( $invalidBecause ),
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

		return $userData;
	}
}