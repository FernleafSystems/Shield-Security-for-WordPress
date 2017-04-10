<?php

if ( !class_exists( 'ICWP_WPSF_Processor_LoginProtect_Gasp', false ) ):

require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'base_wpsf.php' );

class ICWP_WPSF_Processor_LoginProtect_Gasp extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();

		// Add GASP checking to the login form.
		add_action( 'login_form',				array( $this, 'printGaspLoginCheck_Action' ), 100 );
		add_action( 'woocommerce_login_form',	array( $this, 'printGaspLoginCheck_Action' ) );
		add_filter( 'login_form_middle',		array( $this, 'printGaspLoginCheck_Filter' ) );

		// before username/password check (20)
		add_filter( 'authenticate',				array( $this, 'checkLoginForGasp_Filter' ), 12, 3 );

		// apply to user registrations if set to do so.
		if ( $oFO->getIsCheckingUserRegistrations() ) {
			//print the checkbox code:
			add_action( 'register_form',		array( $this, 'printGaspLoginCheck_Action' ) );
			add_action( 'lostpassword_form',	array( $this, 'printGaspLoginCheck_Action' ) );
			
			//verify the checkbox is present:
			add_action( 'register_post',		array( $this, 'checkRegisterForGasp_Action' ), 10, 1 );
			add_action( 'lostpassword_post',	array( $this, 'checkResetPasswordForGasp_Action' ), 10, 1 );
		}
	}

	/**
	 */
	public function printGaspLoginCheck_Action() {
		echo $this->getGaspLoginHtml();
	}

	/**
	 * @return string
	 */
	public function printGaspLoginCheck_Filter() {
		return $this->getGaspLoginHtml();
	}

	/**
	 * @param $oUser
	 * @param $sUsername
	 * @param $sPassword
	 * @return WP_Error
	 */
	public function checkLoginForGasp_Filter( $oUser, $sUsername, $sPassword ) {
		if ( !$this->loadWpFunctionsProcessor()->getIsLoginRequest() ) {
			return $oUser;
		}

		if ( empty( $sUsername ) || is_wp_error( $oUser ) ) {
			return $oUser;
		}
		if ( $this->doGaspChecks( $sUsername, _wpsf__( 'login' ) ) ) {
			return $oUser;
		}
		//This doesn't actually ever get returned because we die() within doGaspChecks()
		return new WP_Error( 'wpsf_gaspfail', _wpsf__( 'G.A.S.P. Checking Failed.' ) );
	}

	/**
	 * @param string $sSanitizedUsername
	 * @return WP_Error
	 */
	public function checkRegisterForGasp_Action( $sSanitizedUsername ) {
		if ( $this->doGaspChecks( $sSanitizedUsername, _wpsf__( 'register' ) ) ) {
			return $sSanitizedUsername;
		}
		//This doesn't actually ever get returned because we die() within doGaspChecks()
		return new WP_Error( 'wpsf_gaspfail', _wpsf__( 'G.A.S.P. Checking Failed.' ) );
	}

	/**
	 * @param string $sSanitizedUsername
	 * @return WP_Error
	 */
	public function checkResetPasswordForGasp_Action( $sSanitizedUsername ) {
		if ( $this->doGaspChecks( $sSanitizedUsername, _wpsf__( 'reset-password' ) ) ) {
			return $sSanitizedUsername;
		}
		//This doesn't actually ever get returned because we die() within doGaspChecks()
		return new WP_Error( 'wpsf_gaspfail', _wpsf__( 'G.A.S.P. Checking Failed.' ) );
	}

	/**
	 * @return string
	 */
	protected function getGaspLoginHtml() {

		$sLabel = _wpsf__( "I'm a human." );
		$sAlert = _wpsf__( "Please check the box to show us you're a human." );

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
		$oFO = $this->getFeatureOptions();
		return $oFO->prefix( $oFO->getGaspKey() );
	}

	/**
	 * @param string $sUsername
	 * @param string $sActionAttempted
	 * @return bool
	 */
	protected function doGaspChecks( $sUsername, $sActionAttempted = 'login' ) {
		$oDp = $this->loadDataProcessor();
		$sGaspCheckBox = $oDp->FetchPost( $this->getGaspCheckboxName() );
		$sHoney = $oDp->FetchPost( 'icwp_wpsf_login_email' );

		if ( empty( $sGaspCheckBox ) ) {
			$sAuditMessage = sprintf( _wpsf__('User "%s" attempted to %s but GASP checkbox was not present.'), $sUsername, $sActionAttempted ).' '._wpsf__('Probably a BOT.');
			$this->addToAuditEntry( $sAuditMessage, 3, $sActionAttempted.'_protect_block_gasp_checkbox' );
			$this->doStatIncrement( $sActionAttempted.'.gasp.checkbox.fail' );

			// We now black mark this IP
			add_filter( $this->getFeatureOptions()->prefix( 'ip_black_mark' ), '__return_true' );

			$this->loadWpFunctionsProcessor()->wpDie( _wpsf__( "You must check that box to say you're not a bot." ) );
			return false;
		}
		else if ( !empty( $sHoney ) ) {
			$sAuditMessage = sprintf( _wpsf__('User "%s" attempted to %s but they were caught by the GASP honeypot.'), $sUsername, $sActionAttempted ).' '._wpsf__('Probably a BOT.');
			$this->addToAuditEntry( $sAuditMessage, 3, $sActionAttempted.'_protect_block_gasp_honeypot' );
			$this->doStatIncrement( $sActionAttempted.'.gasp.honeypot.fail' );

			// We now black mark this IP
			add_filter( $this->getFeatureOptions()->prefix( 'ip_black_mark' ), '__return_true' );

			$this->loadWpFunctionsProcessor()->wpDie( sprintf( _wpsf__( 'You appear to be a bot - terminating %s attempt.' ), $sActionAttempted ) );
			return false;
		}
		return true;
	}
}
endif;
