<?php

if ( class_exists( 'ICWP_WPSF_Processor_LoginProtect_IntentProviderBase', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

abstract class ICWP_WPSF_Processor_LoginProtect_IntentProviderBase extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @var ICWP_WPSF_Processor_LoginProtect_Track
	 */
	private $oLoginTrack;

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();

		if ( $oFO->getIfUseLoginIntentPage() ) {
			add_filter( $oFO->prefix( 'login-intent-form-fields' ), array( $this, 'addLoginIntentField' ) );
			add_action( $oFO->prefix( 'login-intent-validation' ), array( $this, 'validateLoginIntent' ) );
		}

		if ( $this->loadWp()->isRequestUserLogin() || $oFO->getIfSupport3rdParty() ) {
			add_filter( 'authenticate', array( $this, 'processLoginAttempt_Filter' ), 30, 2 );
		}

		// Necessary so we don't show user intent to people without it
		add_filter( $oFO->prefix( 'user_subject_to_login_intent' ), array( $this, 'filterUserSubjectToIntent' ), 10, 2 );

		add_action( 'show_user_profile', array( $this, 'addOptionsToUserProfile' ) );
		add_action( 'personal_options_update', array( $this, 'handleUserProfileSubmit' ) );

		if ( $this->getController()->getIsValidAdminArea( true ) ) {
			add_action( 'edit_user_profile', array( $this, 'addOptionsToUserProfile' ) );
			add_action( 'edit_user_profile_update', array( $this, 'handleEditOtherUserProfileSubmit' ) );
		}
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
			if ( $this->processOtp( $oUser, $this->fetchCodeFromRequest() ) ) {
				$oLoginTrack->addSuccessfulFactor( $sFactor );
				$this->auditLogin( $oUser, true );
			}
			else {
				$oLoginTrack->addUnSuccessfulFactor( $sFactor );
				$this->auditLogin( $oUser, false );
			}
		}
	}

	/**
	 * @return bool
	 */
	public function getCurrentUserHasValidatedProfile() {
		return $this->hasValidatedProfile( $this->loadWpUsers()->getCurrentWpUser() );
	}

	/**
	 * @param WP_User $oUser
	 * @return bool
	 */
	protected function hasValidatedProfile( $oUser ) {
		if ( !( $oUser instanceof WP_User ) ) {
			return false;
		}

		$oWpUsers = $this->loadWpUsers();

		$sKey = $this->getStub().'_validated';
		$bValidated = $oWpUsers->metaVoForUser( $this->prefix(), $oUser->ID )->{$sKey};

		// fallback to old meta
		// 2018-01: needs to be left here for a long time for ensure all users update to new meta.
		if ( is_string( $bValidated ) ) {
			$bValidated = ( $bValidated == 'Y' );
		}
		else if ( is_null( $bValidated ) ) {
			$sOldMetaKey = $this->getFeature()->prefixOptionKey( $sKey );
			// look for the old style meta
			$bValidated = ( $oWpUsers->getUserMeta( $sOldMetaKey, $oUser->ID ) == 'Y' );
			if ( $bValidated ) {
				$oWpUsers->deleteUserMeta( $sOldMetaKey, $oUser->ID );
			}
		}
		$bValidated = $bValidated && $this->hasValidSecret( $oUser );
		$this->setProfileValidated( $oUser, $bValidated );

		return $bValidated;
	}

	/**
	 * @param WP_User $oUser
	 * @return string
	 */
	protected function getSecret( WP_User $oUser ) {
		$oWpUsers = $this->loadWpUsers();

		$sKey = $this->getStub().'_secret';
		$oMeta = $oWpUsers->metaVoForUser( $this->prefix(), $oUser->ID );

		$sSecret = $oMeta->{$sKey};

		// fallback to old meta
		// 2018-01: needs to be left here for a long time for ensure all users update to new meta.
		if ( !$this->isSecretValid( $sSecret ) ) {
			$sOldMetaKey = $this->getFeature()->prefixOptionKey( $sKey );
			$sSecret = $oWpUsers->getUserMeta( $sOldMetaKey, $oUser->ID );
			$oWpUsers->deleteUserMeta( $sOldMetaKey, $oUser->ID );

			if ( $this->isSecretValid( $sSecret ) ) {
				$this->setSecret( $oUser, $sSecret );
			}
			else {
				$sSecret = $this->resetSecret( $oUser );
			}
		}

		return $sSecret;
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
	 * @return string
	 */
	protected function resetSecret( WP_User $oUser ) {
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
	 * @param         $sNewSecret
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
	public function processLoginAttempt_Filter( $oUser ) {
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
	 * @return string
	 */
	protected function getLoginFormParameter() {
		return $this->getFeature()->prefixOptionKey( $this->getStub().'_otp' );
	}

	/**
	 * @return string
	 */
	protected function fetchCodeFromRequest() {
		return esc_attr( trim( $this->loadDP()->request( $this->getLoginFormParameter(), false, '' ) ) );
	}

	/**
	 * @param bool    $bIsSubjectTo
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