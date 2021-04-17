<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use Dolondro\GoogleAuthenticator;
use FernleafSystems\Utilities\Data\Response\StdResponse;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

class GoogleAuth extends BaseProvider {

	const SLUG = 'ga';

	/**
	 * @var GoogleAuthenticator\Secret
	 */
	private $oWorkingSecret;

	public function isProfileActive( \WP_User $user ) :bool {
		return parent::isProfileActive( $user ) && $this->hasValidatedProfile( $user );
	}

	public function getJavascriptVars() :array {
		return [
			'ajax' => [
				'user_ga_toggle' => $this->getMod()->getAjaxActionData( 'user_ga_toggle' ),
			],
		];
	}

	protected function getProviderSpecificRenderData( \WP_User $user ) :array {
		$con = $this->getCon();

		$validatedProfile = $this->hasValidatedProfile( $user );
		return [
			'hrefs'   => [
				'src_chart_url' => $validatedProfile ? '' : $this->getGaRegisterChartUrl( $user ),
			],
			'vars'    => [
				'ga_secret' => $validatedProfile ? $this->getSecret( $user ) : $this->resetSecret( $user ),
			],
			'strings' => [
				'enter_auth_app_code'   => __( 'Enter 6-digit Code from App', 'wp-simple-firewall' ),
				'description_otp_code'  => __( 'Provide the current code generated by your Google Authenticator app.', 'wp-simple-firewall' ),
				'description_chart_url' => __( 'Use your Google Authenticator app to scan this QR code and enter the 6-digit one time password.', 'wp-simple-firewall' ),
				'description_ga_secret' => __( 'If you have a problem with scanning the QR code enter the long code manually into the app.', 'wp-simple-firewall' ),
				'desc_remove'           => __( 'Click to immediately remove Google Authenticator login authentication.', 'wp-simple-firewall' ),
				'label_check_to_remove' => sprintf( __( 'Remove %s', 'wp-simple-firewall' ), __( 'Google Authenticator', 'wp-simple-firewall' ) ),
				'label_enter_code'      => __( 'Google Authenticator Code', 'wp-simple-firewall' ),
				'label_ga_secret'       => __( 'Manual Code', 'wp-simple-firewall' ),
				'label_scan_qr_code'    => __( 'Scan This QR Code', 'wp-simple-firewall' ),
				'title'                 => __( 'Google Authenticator', 'wp-simple-firewall' ),
				'cant_add_other_user'   => sprintf( __( "Sorry, %s may not be added to another user's account.", 'wp-simple-firewall' ), 'Google Authenticator' ),
				'cant_remove_admins'    => sprintf( __( "Sorry, %s may only be removed from another user's account by a Security Administrator.", 'wp-simple-firewall' ), __( 'Google Authenticator', 'wp-simple-firewall' ) ),
				'provided_by'           => sprintf( __( 'Provided by %s', 'wp-simple-firewall' ), $con->getHumanName() ),
				'remove_more_info'      => __( 'Understand how to remove Google Authenticator', 'wp-simple-firewall' ),
				'remove_google_auth'    => __( 'Remove Google Authenticator', 'wp-simple-firewall' )
			],
		];
	}

	/**
	 * @param \WP_User $user
	 * @return string
	 */
	public function getGaRegisterChartUrl( $user ) {
		$sUrl = '';
		if ( !empty( $user ) ) {
			try {
				$sUrl = ( new GoogleAuthenticator\QrImageGenerator\GoogleQrImageGenerator () )
					->generateUri(
						$this->getGaSecret( $user )
					);
			}
			catch ( \InvalidArgumentException $e ) {
			}
		}
		return $sUrl;
	}

	/**
	 * The only thing we can do is REMOVE Google Authenticator from an account that is not our own
	 * But, only admins can do this.  If Security Admin feature is enabled, then only they can do it.
	 * @inheritDoc
	 */
	public function handleEditOtherUserProfileSubmit( \WP_User $user ) {

		// Can only edit other users if you're admin/security-admin
		if ( $this->getCon()->isPluginAdmin() && Services::Request()->post( 'shield_turn_off_ga' ) === 'Y' ) {
			$this->processRemovalFromAccount( $user );
			$msg = __( 'Google Authenticator was successfully removed from the account.', 'wp-simple-firewall' );
			$this->getMod()->setFlashAdminNotice( $msg );
		}
	}

	public function removeGaOnAccount( \WP_User $user ) :StdResponse {
		$this->processRemovalFromAccount( $user );
		$response = new StdResponse();
		$response->success = true;
		$response->msg_text = __( 'Google Authenticator was successfully removed from the account.', 'wp-simple-firewall' );
		return $response;
	}

	public function activateGaOnAccount( \WP_User $user, string $otp ) :StdResponse {
		$response = new StdResponse();
		$response->success = $this->processOtp( $user, $otp );
		if ( $response->success ) {
			$this->setProfileValidated( $user );
			$response->msg_text = sprintf(
				__( '%s was successfully added to your account.', 'wp-simple-firewall' ),
				__( 'Google Authenticator', 'wp-simple-firewall' )
			);
		}
		else {
			$this->resetSecret( $user );
			$response->error_text = __( 'One Time Password (OTP) was not valid.', 'wp-simple-firewall' )
									.' '.__( 'Please try again.', 'wp-simple-firewall' );
		}
		return $response;
	}

	/**
	 * @param \WP_User $user
	 * @return $this
	 */
	protected function processRemovalFromAccount( \WP_User $user ) {
		$this->setProfileValidated( $user, false )
			 ->resetSecret( $user );
		return $this;
	}

	/**
	 * @inheritDoc
	 */
	public function handleUserProfileSubmit( \WP_User $user ) {
		$otp = $this->fetchCodeFromRequest();

		if ( Services::Request()->post( 'shield_turn_off_ga' ) === 'Y' ) {
			$flash = __( 'Google Authenticator was successfully removed from the account.', 'wp-simple-firewall' );
			$this->processRemovalFromAccount( $user );
			$this->getMod()->setFlashAdminNotice( $flash );
			/**
			 * $flash = __( 'An email has been sent to you in order to confirm Google Authenticator removal', 'wp-simple-firewall' );
			 * $flash = __( 'We tried to send an email for you to confirm Google Authenticator removal but it failed.', 'wp-simple-firewall' );
			 */
		}
		elseif ( !empty( $otp ) && !$this->hasValidatedProfile( $user ) ) { // Add GA to profile
			$validOTP = $this->processOtp( $user, $otp );
			if ( $validOTP ) {
				$this->setProfileValidated( $user );
				$flash = sprintf(
					__( '%s was successfully added to your account.', 'wp-simple-firewall' ),
					__( 'Google Authenticator', 'wp-simple-firewall' )
				);
			}
			else {
				$this->resetSecret( $user );
				$flash = __( 'One Time Password (OTP) was not valid.', 'wp-simple-firewall' )
						 .' '.__( 'Please try again.', 'wp-simple-firewall' );
			}
			$this->getMod()->setFlashAdminNotice( $flash, !$validOTP );
		}
	}

	/**
	 * @return array
	 */
	public function getFormField() :array {
		return [
			'name'        => $this->getLoginFormParameter(),
			'type'        => 'text',
			'value'       => '',
			'placeholder' => __( 'Please use your Google Authenticator App to retrieve your code.', 'wp-simple-firewall' ),
			'text'        => __( 'Google Authenticator Code', 'wp-simple-firewall' ),
			'help_link'   => 'https://shsec.io/wpsf42',
			'extras'      => [
				'onkeyup' => "this.value=this.value.replace(/[^\d]/g,'')"
			]
		];
	}

	protected function processOtp( \WP_User $user, string $otp ) :bool {
		return $this->validateGaCode( $user, $otp );
	}

	/**
	 * @param \WP_User $user
	 * @param string   $otp
	 * @return bool
	 */
	public function validateGaCode( \WP_User $user, string $otp ) :bool {
		$valid = false;
		if ( preg_match( '#^[0-9]{6}$#', $otp ) ) {
			try {
				$valid = (bool)( new GoogleAuthenticator\GoogleAuthenticator() )
					->authenticate( $this->getSecret( $user ), $otp );
			}
			catch ( \Exception $e ) {
			}
			catch ( \Psr\Cache\CacheException $e ) {
			}
		}
		return $valid;
	}

	/**
	 * @param \WP_User $user
	 * @param bool     $bIsSuccess
	 */
	protected function auditLogin( \WP_User $user, bool $bIsSuccess ) {
		$this->getCon()->fireEvent(
			$bIsSuccess ? 'googleauth_verified' : 'googleauth_fail',
			[
				'audit' => [
					'user_login' => $user->user_login,
					'method'     => 'Google Authenticator',
				]
			]
		);
	}

	/**
	 * @param \WP_User $user
	 * @return string
	 */
	protected function genNewSecret( \WP_User $user ) {
		try {
			return $this->getGaSecret( $user )->getSecretKey();
		}
		catch ( \InvalidArgumentException $e ) {
			return '';
		}
	}

	/**
	 * @param \WP_User $user
	 * @return GoogleAuthenticator\Secret
	 */
	private function getGaSecret( $user ) {
		if ( !isset( $this->oWorkingSecret ) ) {
			$this->oWorkingSecret = ( new GoogleAuthenticator\SecretFactory() )
				->create(
					sanitize_user( $user->user_login ),
					preg_replace( '#[^0-9a-z]#i', '', Services::WpGeneral()->getSiteName() )
				);
		}
		return $this->oWorkingSecret;
	}

	/**
	 * @param \WP_User $user
	 * @return string
	 */
	protected function getSecret( \WP_User $user ) {
		$sSec = parent::getSecret( $user );
		return empty( $sSec ) ? $this->resetSecret( $user ) : $sSec;
	}

	/**
	 * @param string $secret
	 * @return bool
	 */
	protected function isSecretValid( $secret ) {
		return parent::isSecretValid( $secret ) && ( strlen( $secret ) == 16 );
	}

	public function isProviderEnabled() :bool {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		return $opts->isEnabledGoogleAuthenticator();
	}

	protected function getProviderName() :string {
		return 'Google Authenticator';
	}
}