<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;
use u2flib_server\RegisterRequest;

class U2F extends BaseProvider {

	const SLUG = 'u2f';

	public function setup() {
		add_action( 'admin_enqueue_scripts', function ( $sHook ) {
			if ( in_array( $sHook, [ 'profile.php', ] ) ) {
				$this->enqueueAdminU2f();
			}
		} );
	}

	private function enqueueAdminU2f() {
		$aDeps = [];
		foreach ( [ 'u2f-bundle', 'shield-u2f-admin' ] as $sScript ) {
			wp_enqueue_script(
				$this->getCon()->prefix( $sScript ),
				$this->getCon()->getPluginUrl_Js( $sScript ),
				$aDeps
			);
			$aDeps[] = $this->getCon()->prefix( $sScript );
		}

		try {
			/** @var RegisterRequest $oReq */
			$aParts = wp_parse_url( Services::WpGeneral()->getHomeUrl() );
			$sAppId = sprintf( 'https://%s%s', $aParts[ 'host' ], empty( $aParts[ 'port' ] ) ? '' : ':'.$aParts[ 'port' ] );
			list( $oReq, $signatures ) = ( new \u2flib_server\U2F( $sAppId ) )->getRegisterData( [] );

			wp_localize_script(
				$this->getCon()->prefix( 'shield-u2f-admin' ),
				'icwp_wpsf_vars_u2f',
				[
					'raw_request' => (array)$oReq,
					'request'     => [
						'version'   => $oReq->version,
						'challenge' => $oReq->challenge
					],
					'app_id'      => $oReq->appId,
					'strings'     => [
						'not_supported' => __( 'U2F Security Key registration is not supported in this browser', 'wp-simple-firewall' ),
						'failed'       => __( 'Key registration failed.', 'wp-simple-firewall' )
										  .' '.__( 'Please retry or refresh the page.', 'wp-simple-firewall' ),
						'do_save'       => __( 'Key registration was successful.', 'wp-simple-firewall' )
										   .' '.__( 'Please now save your profile settings.', 'wp-simple-firewall' )
					]
				]
			);
		}
		catch ( \Exception $oE ) {
		}
	}

	/**
	 * @inheritDoc
	 */
	public function renderUserProfileOptions( \WP_User $oUser ) {
		$aData = [
			'strings' => [
				'title'                 => __( 'U2F', 'wp-simple-firewall' ),
				'button_reg_key'        => __( 'Register A New U2F Security Key', 'wp-simple-firewall' ),
				'prompt'                => __( 'Click To Start U2F Security Registration', 'wp-simple-firewall' ),
			]
		];

		return $this->getMod()
					->renderTemplate(
						'/snippets/user/profile/mfa/mfa_u2f.twig',
						Services::DataManipulation()->mergeArraysRecursive( $this->getCommonData( $oUser ), $aData ),
						true
					);
	}

	/**
	 * @inheritDoc
	 */
	public function renderUserEditProfileOptions( \WP_User $oUser ) {
		// Allow no actions to be taken on other user profiles
	}

	/**
	 * @return array
	 */
	public function getFormField() {
		return [
			'name'        => $this->getLoginFormParameter(),
			'type'        => 'text',
			'value'       => '',
			'placeholder' => __( 'Please use your Backup Code to login.', 'wp-simple-firewall' ),
			'text'        => __( 'Login Backup Code', 'wp-simple-firewall' ),
			'help_link'   => '',
		];
	}

	/**
	 * @param \WP_User $oUser
	 * @return bool
	 */
	public function hasValidatedProfile( $oUser ) {
		return $this->hasValidSecret( $oUser );
	}

	/**
	 * @param \WP_User $oUser
	 * @return $this
	 */
	public function postSuccessActions( \WP_User $oUser ) {
		$this->deleteSecret( $oUser );
		$this->sendBackupCodeUsedEmail( $oUser );
		return $this;
	}

	/**
	 * Backup Code are 1-time only and if you have MFA, then we need to remove all the other tracking factors
	 * @param \WP_User $oUser
	 * @param string   $sOtpCode
	 * @return bool
	 */
	protected function processOtp( $oUser, $sOtpCode ) {
		return $this->validateBackupCode( $oUser, $sOtpCode );
	}

	/**
	 * @param \WP_User $oUser
	 * @param string   $sOtpCode
	 * @return bool
	 */
	private function validateBackupCode( $oUser, $sOtpCode ) {
		return wp_check_password( str_replace( '-', '', $sOtpCode ), $this->getSecret( $oUser ) );
	}

	/**
	 * @param \WP_User $oUser
	 * @param bool     $bIsSuccess
	 */
	protected function auditLogin( $oUser, $bIsSuccess ) {
		$this->getCon()->fireEvent(
			$bIsSuccess ? '2fa_backupcode_verified' : '2fa_backupcode_fail',
			[
				'audit' => [
					'user_login' => $oUser->user_login,
					'method'     => 'Backup Code',
				]
			]
		);
	}

	/**
	 * @param \WP_User $oUser
	 * @return string
	 */
	protected function genNewSecret( \WP_User $oUser ) {
		return wp_generate_password( 25, false );
	}

	/**
	 * @return bool
	 */
	public function isProviderEnabled() {
		/** @var LoginGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->isEnabledU2F();
	}

	/**
	 * @param \WP_User $oUser
	 * @param string   $sNewSecret
	 * @return $this
	 */
	protected function setSecret( $oUser, $sNewSecret ) {
		parent::setSecret( $oUser, wp_hash_password( $sNewSecret ) );
		return $this;
	}

	/**
	 * @param \WP_User $oUser
	 */
	private function sendBackupCodeUsedEmail( $oUser ) {
		$aEmailContent = [
			__( 'This is a quick notice to inform you that your Backup Login code was just used.', 'wp-simple-firewall' ),
			__( "Your WordPress account had only 1 backup login code.", 'wp-simple-firewall' )
			.' '.__( "You must go to your profile and regenerate a new code if you want to use this method again.", 'wp-simple-firewall' ),
			'',
			sprintf( '<strong>%s</strong>', __( 'Login Details', 'wp-simple-firewall' ) ),
			sprintf( '%s: %s', __( 'URL', 'wp-simple-firewall' ), Services::WpGeneral()->getHomeUrl() ),
			sprintf( '%s: %s', __( 'Username', 'wp-simple-firewall' ), $oUser->user_login ),
			sprintf( '%s: %s', __( 'IP Address', 'wp-simple-firewall' ), Services::IP()->getRequestIp() ),
			'',
			__( 'Thank You.', 'wp-simple-firewall' ),
		];

		$sTitle = sprintf( __( "Notice: %s", 'wp-simple-firewall' ), __( "Backup Login Code Just Used", 'wp-simple-firewall' ) );
		$this->getMod()
			 ->getEmailProcessor()
			 ->sendEmailWithWrap( $oUser->user_email, $sTitle, $aEmailContent );
	}
}