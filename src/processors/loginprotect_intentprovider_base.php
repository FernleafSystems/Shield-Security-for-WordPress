<?php

abstract class ICWP_WPSF_Processor_LoginProtect_IntentProviderBase extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @var ICWP_WPSF_Processor_LoginProtect_Track
	 */
	private $oLoginTrack;

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();

		$this->getLoginTrack()->addFactorToTrack( $this->getStub() );

		if ( $oFO->getIfUseLoginIntentPage() ) {
			add_filter( $oFO->prefix( 'login-intent-form-fields' ), array( $this, 'addLoginIntentField' ) );
			add_action( $oFO->prefix( 'login-intent-validation' ), array( $this, 'validateLoginIntent' ) );
		}

		if ( $this->loadWp()->isRequestUserLogin() || $oFO->getIfSupport3rdParty() ) {
//			add_filter( 'authenticate', array( $this, 'processLoginAttempt_Filter' ), 30, 2 );
		}

		// Necessary so we don't show user intent to people without it
		add_filter( $oFO->prefix( 'user_subject_to_login_intent' ), array( $this, 'filterUserSubjectToIntent' ), 10, 2 );

		add_action( 'show_user_profile', array( $this, 'addOptionsToUserProfile' ) );
		add_action( 'personal_options_update', array( $this, 'handleUserProfileSubmit' ) );

		if ( $this->getCon()->isPluginAdmin() ) {
			add_action( 'edit_user_profile', array( $this, 'addOptionsToUserEditProfile' ) );
			add_action( 'edit_user_profile_update', array( $this, 'handleEditOtherUserProfileSubmit' ) );
		}
	}

	/**
	 * @param string  $sUsername
	 * @param WP_User $oUser
	 */
	public function onWpLogin( $sUsername, $oUser ) {
		$this->processLoginAttempt( $oUser );
	}

	/**
	 * @param string $sCookie
	 * @param int    $nExpire
	 * @param int    $nExpiration
	 * @param int    $nUserId
	 */
	public function onWpSetLoggedInCookie( $sCookie, $nExpire, $nExpiration, $nUserId ) {
		$this->processLoginAttempt( $this->loadWpUsers()->getUserById( $nUserId ) );
	}

	/**
	 * @param WP_User $oUser
	 */
	public function validateLoginIntent( $oUser ) {
		$oLoginTrack = $this->getLoginTrack();

		$sFactor = $this->getStub();
		if ( !$this->isProfileReady( $oUser ) ) {
			$oLoginTrack->removeFactorToTrack( $sFactor );
		}
		else {
			$sReqOtpCode = $this->fetchCodeFromRequest();
			$bOtpSuccess = $this->processOtp( $oUser, $sReqOtpCode );
			if ( $bOtpSuccess ) {
				$oLoginTrack->addSuccessfulFactor( $sFactor );
			}
			else {
				$oLoginTrack->addUnSuccessfulFactor( $sFactor );
			}

			$this->postOtpProcessAction( $oUser, $bOtpSuccess, !empty( $sReqOtpCode ) );
		}
	}

	/**
	 * @return bool
	 */
	public function getCurrentUserHasValidatedProfile() {
		return $this->hasValidatedProfile( $this->loadWpUsers()->getCurrentWpUser() );
	}

	/**
	 * @since 6.9.0 removed fallback to old user meta
	 * @param WP_User $oUser
	 * @return bool
	 */
	protected function hasValidatedProfile( $oUser ) {
		$sKey = $this->getStub().'_validated';
		return ( $oUser instanceof WP_User )
			   && $this->hasValidSecret( $oUser )
			   && $this->loadWpUsers()->metaVoForUser( $this->prefix(), $oUser->ID )->{$sKey} === true;
	}

	/**
	 * @since 6.9.0 removed fallback to old user meta
	 * @param WP_User $oUser
	 * @return string
	 */
	protected function getSecret( WP_User $oUser ) {
		$sKey = $this->getStub().'_secret';
		$oMeta = $this->loadWpUsers()
					  ->metaVoForUser( $this->prefix(), $oUser->ID );
		$sSecret = $oMeta->{$sKey};
		return empty( $sSecret ) ? '' : $oMeta->{$sKey};
	}

	/**
	 * @param WP_User $oUser
	 * @return bool
	 */
	protected function isProfileReady( WP_User $oUser ) {
		return $this->hasValidatedProfile( $oUser ) && $this->hasValidSecret( $oUser );
	}

	/**
	 * @param WP_User $oUser
	 * @return bool
	 */
	protected function hasValidSecret( WP_User $oUser ) {
		return $this->isSecretValid( $this->getSecret( $oUser ) );
	}

	/**
	 * @param string $sSecret
	 * @return bool
	 */
	protected function isSecretValid( $sSecret ) {
		return !empty( $sSecret ) && is_string( $sSecret );
	}

	/**
	 * @param WP_User $oUser
	 * @return $this
	 */
	public function deleteSecret( $oUser ) {
		$oMeta = $this->loadWpUsers()->metaVoForUser( $this->prefix(), $oUser->ID );
		$sKey = $this->getStub().'_secret';
		$oMeta->{$sKey} = null;
		return $this;
	}

	/**
	 * @param WP_User $oUser
	 * @return string
	 */
	public function resetSecret( WP_User $oUser ) {
		$sNewSecret = $this->genNewSecret();
		$this->setSecret( $oUser, $sNewSecret );
		return $sNewSecret;
	}

	/**
	 * @param WP_User $oUser
	 * @param bool    $bValidated set true for validated, false for invalidated
	 * @return $this
	 */
	public function setProfileValidated( $oUser, $bValidated = true ) {
		$sKey = $this->getStub().'_validated';
		$oMeta = $this->loadWpUsers()->metaVoForUser( $this->prefix(), $oUser->ID );
		$oMeta->{$sKey} = $bValidated;
		return $this;
	}

	/**
	 * @param WP_User $oUser
	 * @param string  $sNewSecret
	 * @return $this
	 */
	protected function setSecret( $oUser, $sNewSecret ) {
		$oMeta = $this->loadWpUsers()->metaVoForUser( $this->prefix(), $oUser->ID );
		$sKey = $this->getStub().'_secret';
		$oMeta->{$sKey} = $sNewSecret;
		return $this;
	}

	/**
	 * @return string
	 */
	protected function genNewSecret() {
		return '';
	}

	/**
	 * @param WP_User $oUser
	 * @param string  $sOtpCode
	 * @return bool
	 */
	abstract protected function processOtp( $oUser, $sOtpCode );

	/**
	 * Look to LoginTracker
	 * @return string
	 */
	abstract protected function getStub();

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile, so we can use "current user"
	 * functions.  Otherwise we need to be careful of mixing up users.
	 * @param WP_User $oUser
	 */
	public function addOptionsToUserProfile( $oUser ) {
	}

	/**
	 * ONLY TO BE HOOKED TO USER PROFILE EDIT
	 * @param WP_User $oUser
	 */
	public function addOptionsToUserEditProfile( $oUser ) {
		$this->addOptionsToUserProfile( $oUser );
	}

	/**
	 * The only thing we can do is REMOVE Google Authenticator from an account that is not our own
	 * But, only admins can do this.  If Security Admin feature is enabled, then only they can do it.
	 * @param int $nSavingUserId
	 */
	public function handleEditOtherUserProfileSubmit( $nSavingUserId ) {
	}

	/**
	 * @param WP_User $oUser
	 */
	protected function processRemovalFromAccount( $oUser ) {
	}

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile,
	 * so we can use "current user" functions.  Otherwise we need to be careful of mixing up users.
	 * @param int $nSavingUserId
	 */
	public function handleUserProfileSubmit( $nSavingUserId ) {
	}

	/**
	 * @param WP_Error|WP_User $oUser
	 * @return WP_Error|WP_User
	 */
	public function processLoginAttempt( $oUser ) {
		return $oUser;
	}

	/**
	 * @param array $aFields
	 * @return array
	 */
	abstract public function addLoginIntentField( $aFields );

	/**
	 * @param WP_User $oUser
	 * @param bool    $bIsSuccess
	 */
	abstract protected function auditLogin( $oUser, $bIsSuccess );

	/**
	 * @param WP_User $oUser
	 * @param bool    $bIsOtpSuccess
	 * @param bool    $bOtpProvided - whether a OTP was actually provided
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
		return $this->getMod()->prefixOptionKey( $this->getStub().'_otp' );
	}

	/**
	 * @return string
	 */
	protected function fetchCodeFromRequest() {
		return esc_attr( $this->loadRequest()->request( $this->getLoginFormParameter(), false, '' ) );
	}

	/**
	 * @param bool $bIsSubjectTo
	 * @param WP_User $oUser
	 * @return bool
	 */
	public function filterUserSubjectToIntent( $bIsSubjectTo, $oUser ) {
		return ( $bIsSubjectTo || $this->hasValidatedProfile( $oUser ) );
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Track
	 */
	public function getLoginTrack() {
		return $this->oLoginTrack;
	}

	/**
	 * @param ICWP_WPSF_Processor_LoginProtect_Track $oLoginTrack
	 * @return $this
	 */
	public function setLoginTrack( $oLoginTrack ) {
		$this->oLoginTrack = $oLoginTrack;
		return $this;
	}
}