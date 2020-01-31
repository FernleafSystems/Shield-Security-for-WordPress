<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseProvider {

	use Modules\ModConsumer;
	const SLUG = '';

	/**
	 * @param \WP_User $oUser
	 * @return bool
	 */
	public function validateLoginIntent( \WP_User $oUser ) {
		$bOtpSuccess = false;
		if ( $this->isProfileActive( $oUser ) ) {
			$sReqOtpCode = $this->fetchCodeFromRequest();
			$bOtpSuccess = $this->processOtp( $oUser, $sReqOtpCode );
			$this->postOtpProcessAction( $oUser, $bOtpSuccess, !empty( $sReqOtpCode ) );
		}
		return $bOtpSuccess;
	}

	/**
	 * @param \WP_User $oUser
	 * @return string
	 */
	protected function getSecret( \WP_User $oUser ) {
		$sSecret = $this->getCon()->getUserMeta( $oUser )->{static::SLUG.'_secret'};
		return empty( $sSecret ) ? '' : $sSecret;
	}

	/**
	 * @param \WP_User $oUser
	 * @return bool
	 */
	public function hasValidatedProfile( $oUser ) {
		return $this->getCon()->getUserMeta( $oUser )->{static::SLUG.'_validated'} === true;
	}

	/**
	 * @param \WP_User $oUser
	 * @return bool
	 */
	protected function hasValidSecret( \WP_User $oUser ) {
		return $this->isSecretValid( $this->getSecret( $oUser ) );
	}

	/**
	 * @param \WP_User $oUser
	 * @return bool
	 */
	protected function isEnforced( $oUser ) {
		return false;
	}

	/**
	 * @param \WP_User $oUser
	 * @return bool
	 */
	public function isProfileActive( \WP_User $oUser ) {
		return $this->hasValidSecret( $oUser );
	}

	/**
	 * @param \WP_User $oUser
	 * @return bool
	 */
	public function isProviderAvailableToUser( \WP_User $oUser ) {
		return $this->isProviderEnabled();
	}

	/**
	 * @return bool
	 */
	abstract public function isProviderEnabled();

	/**
	 * @param string $sSecret
	 * @return bool
	 */
	protected function isSecretValid( $sSecret ) {
		return !empty( $sSecret ) && is_string( $sSecret );
	}

	/**
	 * @param \WP_User $oUser
	 * @return $this
	 */
	public function deleteSecret( $oUser ) {
		$this->getCon()
			 ->getUserMeta( $oUser )->{static::SLUG.'_secret'} = null;
		return $this;
	}

	/**
	 * @param \WP_User $oUser
	 * @return string
	 */
	public function resetSecret( \WP_User $oUser ) {
		$sNewSecret = $this->genNewSecret( $oUser );
		$this->setSecret( $oUser, $sNewSecret );
		return $sNewSecret;
	}

	/**
	 * @param \WP_User $oUser
	 * @param bool     $bValidated set true for validated, false for invalidated
	 * @return $this
	 */
	public function setProfileValidated( $oUser, $bValidated = true ) {
		$this->getCon()
			 ->getUserMeta( $oUser )->{static::SLUG.'_validated'} = $bValidated;
		return $this;
	}

	/**
	 * @param \WP_User $oUser
	 * @param string   $sNewSecret
	 * @return $this
	 */
	protected function setSecret( $oUser, $sNewSecret ) {
		$this->getCon()
			 ->getUserMeta( $oUser )->{static::SLUG.'_secret'} = $sNewSecret;
		return $this;
	}

	/**
	 * @param \WP_User $oUser
	 * @return string
	 */
	protected function genNewSecret( \WP_User $oUser ) {
		return '';
	}

	/**
	 * @param \WP_User $oUser
	 * @param string   $sOtpCode
	 * @return bool
	 */
	abstract protected function processOtp( $oUser, $sOtpCode );

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile, so we can use "current user"
	 * functions.  Otherwise we need to be careful of mixing up users.
	 * @param \WP_User $oUser
	 * @return string
	 */
	public function renderUserProfileOptions( \WP_User $oUser ) {
		return '';
	}

	/**
	 * ONLY TO BE HOOKED TO USER PROFILE EDIT
	 * @param \WP_User $oUser
	 * @return string
	 */
	public function renderUserEditProfileOptions( \WP_User $oUser ) {
		return $this->renderUserProfileOptions( $oUser );
	}

	/**
	 * @param \WP_User $oUser
	 */
	public function handleEditOtherUserProfileSubmit( \WP_User $oUser ) {
	}

	/**
	 * @param \WP_User $oUser
	 */
	protected function processRemovalFromAccount( $oUser ) {
	}

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile,
	 * so we can use "current user" functions.  Otherwise we need to be careful of mixing up users.
	 * @param \WP_User $oUser
	 */
	public function handleUserProfileSubmit( \WP_User $oUser ) {
	}

	/**
	 * @param \WP_User $oUser
	 */
	public function captureLoginAttempt( $oUser ) {
	}

	/**
	 * @return array
	 */
	abstract public function getFormField();

	/**
	 * @param \WP_User $oUser
	 * @param bool     $bIsSuccess
	 */
	abstract protected function auditLogin( $oUser, $bIsSuccess );

	/**
	 * @param \WP_User $oUser
	 * @param bool     $bIsOtpSuccess
	 * @param bool     $bOtpProvided - whether a OTP was actually provided
	 * @return $this
	 */
	protected function postOtpProcessAction( $oUser, $bIsOtpSuccess, $bOtpProvided ) {
		if ( $bOtpProvided ) {
			$this->auditLogin( $oUser, $bIsOtpSuccess );
		}
		return $this;
	}

	/**
	 * @return string
	 */
	protected function getLoginFormParameter() {
		return $this->getCon()->prefixOption( static::SLUG.'_otp' );
	}

	/**
	 * @return string
	 */
	protected function fetchCodeFromRequest() {
		return esc_attr( Services::Request()->request( $this->getLoginFormParameter(), false, '' ) );
	}

	/**
	 * @param \WP_User $oUser
	 * @return array
	 */
	protected function getCommonData( \WP_User $oUser ) {
		return [
			'flags'   => [
				'has_validated_profile' => $this->hasValidatedProfile( $oUser ),
				'is_enforced'           => $this->isEnforced( $oUser ),
				'is_profile_active'     => $this->isProfileActive( $oUser ),
				'is_my_user_profile'    => $oUser->ID == Services::WpUsers()->getCurrentWpUserId(),
				'i_am_valid_admin'      => $this->getCon()->isPluginAdmin(),
				'user_to_edit_is_admin' => Services::WpUsers()->isUserAdmin( $oUser ),
			],
			'vars'    => [
				'otp_field_name' => $this->getLoginFormParameter(),
			],
			'strings' => [
				'is_enforced' => __( 'This setting is enforced by your security administrator.', 'wp-simple-firewall' ),
			],
		];
	}
}