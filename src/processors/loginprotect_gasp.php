<?php

if ( class_exists( 'ICWP_WPSF_Processor_LoginProtect_Gasp', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_Processor_LoginProtect_Gasp extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();

		// Add GASP checking to the login form.
		add_action( 'login_form', array( $this, 'printLoginFormItems' ), 100 );
		add_filter( 'login_form_middle', array( $this, 'provideLoginFormItems' ) );

		// before username/password check (20)
		add_filter( 'authenticate', array( $this, 'checkReqWpLogin' ), 12, 2 );

		$b3rdParty = $oFO->getIfSupport3rdParty();
		if ( $b3rdParty ) {
			add_action( 'woocommerce_login_form', array( $this, 'printLoginFormItems' ), 10 );
			add_action( 'woocommerce_register_form', array( $this, 'printLoginFormItems' ), 10 );
			add_action( 'edd_login_fields_after', array( $this, 'printLoginFormItems' ), 10 );
		}

		// apply to user registrations if set to do so.
		if ( $oFO->getIsCheckingUserRegistrations() ) {
			//print the checkbox code:
			add_action( 'register_form', array( $this, 'printLoginFormItems' ) );
			add_action( 'lostpassword_form', array( $this, 'printLoginFormItems' ) );

			//verify the checkbox is present:
			add_action( 'register_post', array( $this, 'checkReqRegistration_Wp' ), 10, 1 );
			add_action( 'lostpassword_post', array( $this, 'checkReqPasswordReset_Wp' ), 10 );

			if ( $b3rdParty ) {
				// Easy Digital Downloads
				add_action( 'edd_register_form_fields_before_submit', array( $this, 'printLoginFormItems' ), 10 );

				// Buddypress custom registration page.
				add_action( 'bp_before_registration_submit_buttons', array( $this, 'printLoginFormItems' ), 10 );
				add_action( 'bp_signup_validate', array( $this, 'checkReqRegistration_Wp' ), 10 );

				// Check Woocommerce actions
				add_action( 'woocommerce_lostpassword_form', array( $this, 'printLoginFormItems' ), 10 );
				add_action( 'woocommerce_process_registration_errors', array( $this, 'checkRequestWooRegistration' ), 10, 2 );
			}
		}
	}

	/**
	 * @return string
	 */
	protected function buildLoginFormItems() {
		return $this->getGaspLoginHtml();
	}

	/**
	 */
	public function printLoginFormItems() {
		echo $this->buildLoginFormItems();
	}

	/**
	 * @return string
	 */
	public function provideLoginFormItems() {
		return $this->buildLoginFormItems();
	}

	/**
	 * @param null|WP_User|WP_Error $oUser
	 * @param string                $sUsername
	 * @return WP_Error
	 */
	public function checkReqWpLogin( $oUser, $sUsername ) {
		if ( $this->loadWp()->isRequestUserLogin() && !empty( $sUsername ) && !is_wp_error( $oUser ) ) {

			try {
				$this->doGaspChecks( $sUsername, _wpsf__( 'login' ) );
			}
			catch ( Exception $oE ) {
				$this->loadWp()->wpDie( $oE->getMessage() );
			}
		}
		return $oUser;
	}

	/**
	 * @param WP_Error $oErrors
	 * @param string   $sUsername
	 */
	public function checkRequestWooRegistration( $oErrors, $sUsername ) {
		try {
			$this->doGaspChecks( sanitize_user( $sUsername ), 'woo-register' );
		}
		catch ( Exception $oE ) {
			$this->loadWp()->wpDie( $oE->getMessage() );
		}
	}

	/**
	 * @param string $sSanitizedUsername
	 * @return void
	 */
	public function checkReqRegistration_Wp( $sSanitizedUsername ) {
		try {
			$this->doGaspChecks( $sSanitizedUsername, 'register' );
		}
		catch ( Exception $oE ) {
			$this->loadWp()->wpDie( $oE->getMessage() );
		}
	}

	/**
	 * @return void
	 */
	public function checkReqPasswordReset_Wp() {
		$sSanitizedUsername = sanitize_user( $this->loadDP()->post( 'user_login', '' ) );
		try {
			$this->doGaspChecks( $sSanitizedUsername, 'reset-password' );
		}
		catch ( Exception $oE ) {
			$this->loadWp()->wpDie( $oE->getMessage() );
		}
	}

	/**
	 * @return string
	 */
	private function getGaspLoginHtml() {

		$sLabel = $this->getTextImAHuman();
		$sAlert = $this->getTextPleaseCheckBox();

		$sUniqId = preg_replace( '#[^a-zA-Z0-9]#', '', apply_filters( 'icwp_shield_lp_gasp_uniqid', uniqid() ) );
		$sUniqElem = 'icwp_wpsf_login_p'.$sUniqId;

		$sStyles = '
			<style>
				#'.$sUniqElem.' {
					clear:both;
					border: 1px solid #dddddd;
					padding: 6px 8px 4px 10px;
					margin: 0 0 12px !important;
					border-radius: 2px;
					background-color: #f9f9f9;
				}
				#'.$sUniqElem.' input {
					margin-right: 5px;
				}
				#'.$sUniqElem.' label {
					display: block;
				}
			</style>
		';
		$sHtml =
			$sStyles.
			'<p id="'.$sUniqElem.'" class="icwpImHuman_'.$sUniqId.'"></p>
			<script type="text/javascript">
				var icwp_wpsf_login_p'.$sUniqId.'		= document.getElementById("'.$sUniqElem.'");
				var icwp_wpsf_login_cb'.$sUniqId.'		= document.createElement("input");
				var icwp_wpsf_login_lb'.$sUniqId.'		= document.createElement("label");
				var icwp_wpsf_login_text'.$sUniqId.'	= document.createTextNode(" '.$sLabel.'");
				icwp_wpsf_login_cb'.$sUniqId.'.type		= "checkbox";
				icwp_wpsf_login_cb'.$sUniqId.'.id		= "'.$this->getGaspCheckboxName().'";
				icwp_wpsf_login_cb'.$sUniqId.'.name		= "'.$this->getGaspCheckboxName().'";
				icwp_wpsf_login_p'.$sUniqId.'.appendChild( icwp_wpsf_login_lb'.$sUniqId.' );
				icwp_wpsf_login_lb'.$sUniqId.'.appendChild( icwp_wpsf_login_cb'.$sUniqId.' );
				icwp_wpsf_login_lb'.$sUniqId.'.appendChild( icwp_wpsf_login_text'.$sUniqId.' );
				var frm = icwp_wpsf_login_cb'.$sUniqId.'.form;

				frm.onsubmit = icwp_wpsf_login_it'.$sUniqId.';
				function icwp_wpsf_login_it'.$sUniqId.'() {
					if( icwp_wpsf_login_cb'.$sUniqId.'.checked != true ){
						alert( "'.$sAlert.'" );
						return false;
					}
					return true;
				}
			</script>
			<noscript>'._wpsf__( 'You MUST enable Javascript to be able to login' ).'</noscript>
			<input type="hidden" id="icwp_wpsf_login_email" name="icwp_wpsf_login_email" value="" />
		';

		return $sHtml;
	}

	/**
	 * @return string
	 */
	protected function getGaspCheckboxName() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();
		return $oFO->prefix( $oFO->getGaspKey() );
	}

	/**
	 * @param string $sUsername
	 * @param string $sActionAttempted - one of 'login', 'register', 'reset-password'
	 * @return bool - true if validation successful
	 * @throws Exception
	 */
	protected function doGaspChecks( $sUsername, $sActionAttempted = 'login' ) {
		$oDp = $this->loadDP();
		$sGaspCheckBox = $oDp->post( $this->getGaspCheckboxName() );
		$sHoney = $oDp->post( 'icwp_wpsf_login_email' );

		$bValid = false;
		$sDieMessage = '';
		if ( empty( $sGaspCheckBox ) ) {
			$sAuditMessage = sprintf(
								 _wpsf__( 'User "%s" attempted to %s but GASP checkbox was not present.' ),
								 empty( $sUsername ) ? 'unknown' : $sUsername, $sActionAttempted
							 ).' '._wpsf__( 'Probably a BOT.' );
			$this->addToAuditEntry( $sAuditMessage, 3, $sActionAttempted.'_protect_block_gasp_checkbox' );
			$this->doStatIncrement( $sActionAttempted.'.gasp.checkbox.fail' );
			$sDieMessage = _wpsf__( "You must check that box to say you're not a bot." );
		}
		else if ( !empty( $sHoney ) ) {
			$sAuditMessage = sprintf(
								 _wpsf__( 'User "%s" attempted to %s but they were caught by the GASP honeypot.' ),
								 empty( $sUsername ) ? 'unknown' : $sUsername, $sActionAttempted
							 ).' '._wpsf__( 'Probably a BOT.' );
			$this->addToAuditEntry( $sAuditMessage, 3, $sActionAttempted.'_protect_block_gasp_honeypot' );
			$this->doStatIncrement( $sActionAttempted.'.gasp.honeypot.fail' );
			$sDieMessage = sprintf( _wpsf__( 'You appear to be a bot - terminating %s attempt.' ), $sActionAttempted );
		}
		else {
			$bValid = true;
		}

		if ( !$bValid ) {
			/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
			$oFO = $this->getFeature();
			$oFO->setOptInsightsAt( sprintf( 'last_%s_block_at', $sActionAttempted ) );
			$this->setIpTransgressed(); // We now black mark this IP
			throw new Exception( $sDieMessage );
		}

		return $bValid;
	}

	/**
	 * @return string
	 */
	protected function getTextImAHuman() {
		return $this->getFeature()->getTextOpt( 'text_imahuman' );
	}

	/**
	 * @return string
	 */
	protected function getTextPleaseCheckBox() {
		return $this->getFeature()->getTextOpt( 'text_pleasecheckbox' );
	}
}