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

		add_action( 'personal_options_update', array( $this, 'handleUserProfileSubmit' ) );
		add_action( 'show_user_profile', array( $this, 'addOptionsToUserProfile' ) );

		if ( $this->getController()->getIsValidAdminArea( true ) ) {
			add_action( 'edit_user_profile_update', array( $this, 'handleEditOtherUserProfileSubmit' ) );
			add_action( 'edit_user_profile', array( $this, 'addOptionsToUserProfile' ) );
		}
	}

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
	 */
	public function validateLoginIntent() { }

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
	abstract protected function getLoginFormParameter();

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