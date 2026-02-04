<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Registration;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Verify\Email;

class EmailValidate {

	use ExecOnce;
	use PluginControllerConsumer;

	private $track;

	protected function canRun() :bool {
		return !self::con()->this_req->request_bypasses_all_restrictions
			   && !empty( self::con()->comps->opts_lookup->getEmailValidateChecks() );
	}

	protected function run() {
		add_filter( 'wp_pre_insert_user_data', [ $this, 'validateNewUserEmail' ] );
	}

	/**
	 * @param array|mixed $userData
	 * @return array
	 */
	public function validateNewUserEmail( $userData ) {
		$con = self::con();

		$email = $userData[ 'user_email' ] ?? '';

		if ( !\is_array( $this->track ) ) {
			$this->track = [];
		}

		// This hook seems to be called twice on any given registration.
		if ( !empty( $email ) && !\in_array( $email, $this->track ) ) {
			$this->track[] = $email;

			$invalidBecause = null;
			if ( !is_email( $email ) ) {
				$invalidBecause = 'syntax';
			}
			else {
				$apiToken = $con->comps->api_token->getToken();
				if ( !empty( $apiToken ) ) {
					$checks = $con->comps->opts_lookup->getEmailValidateChecks();
					$verifys = ( new Email( $apiToken ) )->getEmailVerification( $email );
					if ( \is_array( $verifys ) ) {
						foreach ( $verifys as $verifyKey => $valid ) {
							if ( !$valid && \in_array( $verifyKey, $checks ) ) {
								$invalidBecause = $verifyKey;
								break;
							}
						}
					}
				}
			}

			if ( !empty( $invalidBecause ) ) {
				$opt = $con->opts->optGet( 'reg_email_validate' );
				$con->fireEvent(
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