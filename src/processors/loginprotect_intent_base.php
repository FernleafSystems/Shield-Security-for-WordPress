<?php

if ( !class_exists( 'ICWP_WPSF_Processor_LoginProtect_IntentBase', false ) ):

require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'base_wpsf.php' );

abstract class ICWP_WPSF_Processor_LoginProtect_IntentBase extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @var ICWP_WPSF_Processor_LoginProtect_Track
	 */
	private $oLoginTrack;

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();

		if ( $oFO->getIfUseLoginIntentPage() ) {
			add_filter( $oFO->doPluginPrefix( 'login-intent-form-fields' ), array( $this, 'addLoginIntentField' ) );
			add_action( $oFO->doPluginPrefix( 'login-intent-validation' ), array( $this, 'validateLoginIntent' ) );
		}
		else {
			// after User has authenticated email/username/password
			add_filter( 'authenticate', array( $this, 'checkLoginForCode_Filter' ), 23, 2 );
			add_action( 'login_form', array( $this, 'printLoginField' ) );
		}

		add_action( 'show_user_profile', array( $this, 'addOptionsToUserProfile' ) );
		add_action( 'personal_options_update', array( $this, 'handleUserProfileSubmit' ) );

		if ( $this->getController()->getIsValidAdminArea( true ) ) {
			add_action( 'edit_user_profile', array( $this, 'addOptionsToUserProfile' ) );
			add_action( 'edit_user_profile_update', array( $this, 'handleEditOtherUserProfileSubmit' ) );
		}
	}

	/**
	 */
	public function validateLoginIntent() {
		$oLoginTrack = $this->getLoginTrack();
		$oLoginTrack->addSuccessfulFactor( $this->getStub() );

		$oUser = $this->loadWpUsersProcessor()->getCurrentWpUser();
		if ( $this->hasValidatedProfile( $oUser ) && !$this->processOtp( $oUser, $this->fetchCodeFromRequest() ) ) {
			$oLoginTrack->addUnSuccessfulFactor( $this->getStub() );
		}
	}

	/**
	 * @param WP_User $oUser
	 * @return bool
	 */
	protected function hasValidatedProfile( $oUser ) {
		return ( $this->loadWpUsersProcessor()->getUserMeta( $this->getFeatureOptions()->prefixOptionKey( $this->getStub().'_validated' ), $oUser->ID ) == 'Y' );
	}

	/**
	 * @param WP_User $oUser
	 * @return string
	 */
	protected function getSecret( WP_User $oUser ) {
		$oWpUser = $this->loadWpUsersProcessor();
		$sSecret = $oWpUser->getUserMeta( $this->getFeatureOptions()->prefixOptionKey( $this->getStub().'_secret' ), $oUser->ID );
		if ( empty( $sSecret ) ) {
			$this->resetSecret( $oUser );
		}
		return $sSecret;
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
	 * @param bool  $bValidated set true for validated, false for invalidated
	 * @return $this
	 */
	protected function setProfileValidated( $oUser, $bValidated = true ) {
		$this->loadWpUsersProcessor()
			 ->updateUserMeta(
				 $this->getFeatureOptions()->prefixOptionKey( $this->getStub().'_validated' ),
				 $bValidated ? 'Y' : 'N',
				 $oUser->ID
			 );
		return $this;
	}

	/**
	 * @param WP_User $oUser
	 * @param $sNewSecret
	 * @return $this
	 */
	protected function setSecret( $oUser, $sNewSecret ) {
		$this->loadWpUsersProcessor()
			 ->updateUserMeta(
				 $this->getFeatureOptions()->prefixOptionKey( $this->getStub().'_secret' ),
				 $sNewSecret,
				 $oUser->ID
			 );
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
	public function addOptionsToUserProfile( $oUser ) {}

	/**
	 * The only thing we can do is REMOVE Google Authenticator from an account that is not our own
	 * But, only admins can do this.  If Security Admin feature is enabled, then only they can do it.
	 *
	 * @param int $nSavingUserId
	 */
	public function handleEditOtherUserProfileSubmit( $nSavingUserId ) { }

	/**
	 * @param WP_User $oSavingUser
	 */
	protected function processRemovalFromAccount( $oSavingUser ) { }

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile,
	 * so we can use "current user" functions.  Otherwise we need to be careful of mixing up users.
	 *
	 * @param int $nSavingUserId
	 */
	public function handleUserProfileSubmit( $nSavingUserId ) {}

	/**
	 * @param WP_User $oUser
	 * @return WP_Error|WP_User
	 */
	abstract public function checkLoginForCode_Filter( $oUser );

	/**
	 * @param array $aFields
	 * @return array
	 */
	abstract public function addLoginIntentField( $aFields );

	/**
	 */
	public function printLoginField() {
		echo $this->getLoginFormField();
	}

	/**
	 * @return string
	 */
	abstract protected function getLoginFormField();

	/**
	 * @return string
	 */
	protected function getLoginFormParameter() {
		return $this->getFeatureOptions()->prefixOptionKey( $this->getStub().'_otp' );
	}

	/**
	 * @return string
	 */
	protected function fetchCodeFromRequest() {
		return esc_attr( trim( $this->loadDataProcessor()->FetchRequest( $this->getLoginFormParameter(), false, '' ) ) );
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
endif;