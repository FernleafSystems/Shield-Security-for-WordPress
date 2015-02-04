<?php
/**
 * Copyright (c) 2015 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

if ( !class_exists( 'ICWP_WPSF_Processor_LoginProtect_V6', false ) ):

require_once( dirname(__FILE__).ICWP_DS.'base.php' );

class ICWP_WPSF_Processor_LoginProtect_V6 extends ICWP_WPSF_Processor_Base {

	/**
	 * @var ICWP_WPSF_Processor_LoginProtect_Gasp
	 */
	protected $oProcessorGasp;

	/**
	 * @var ICWP_WPSF_Processor_LoginProtect_WpLogin
	 */
	protected $oProcessorWpLogin;

	/**
	 * @var ICWP_WPSF_Processor_LoginProtect_Cooldown
	 */
	protected $oProcessorCooldown;

	/**
	 * @var ICWP_WPSF_Processor_LoginProtect_TwoFactorAuth
	 */
	protected $oProcessorTwoFactor;

	/**
	 * @var ICWP_WPSF_Processor_LoginProtect_Yubikey
	 */
	protected $oProcessorYubikey;

	/**
	 * @param ICWP_WPSF_FeatureHandler_LoginProtect $oFeatureOptions
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_LoginProtect $oFeatureOptions ) {
		parent::__construct( $oFeatureOptions );
		$this->reset();
	}

	/**
	 * @return bool|void
	 */
	public function getIsLogging() {
		return $this->getIsOption( 'enable_login_protect_log', 'Y' );
	}

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();
		$oWp = $this->loadWpFunctionsProcessor();

		// XML-RPC Compatibility
		if ( $oWp->getIsXmlrpc() && $this->getIsOption( 'enable_xmlrpc_compatibility', 'Y' ) ) {
			return true;
		}

		// check for remote posting before anything else.
		if ( $oWp->getIsLoginRequest() && $this->getIsOption( 'enable_prevent_remote_post', 'Y' ) ) {
			add_filter( 'authenticate', array( $this, 'checkRemotePostLogin_Filter' ), 9, 3);
		}

		// Add GASP checking to the login form.
		if ( $this->getIsOption( 'enable_login_gasp_check', 'Y' ) ) {
			$this->getProcessorGasp()->run();
		}

		if ( $oFO->getIsCustomLoginPathEnabled() ) {
			$this->getProcessorWpLogin()->run();
		}

		if ( ( $this->getOption( 'login_limit_interval' ) > 0 ) && $oWp->getIsLoginRequest() ) {
			$this->getProcessorCooldown()->run();
		}

		// check for Yubikey auth after user is authenticated with WordPress.
		if ( $this->getIsOption( 'enable_yubikey', 'Y' ) ) {
			$this->getProcessorYubikey()->run();
		}

		if ( $oFO->getIsTwoFactorAuthOn() ) {
			$this->getProcessorTwoFactor()->run();
		}

		add_filter( 'wp_login_errors', array( $this, 'addLoginMessage' ) );
		return true;
	}

	/**
	 * @param WP_Error $oError
	 * @return WP_Error
	 */
	public function addLoginMessage( $oError ) {

		if ( ! $oError instanceof WP_Error ) {
			$oError = new WP_Error();
		}

		$oDp = $this->loadDataProcessor();
		$sForceLogout = $oDp->FetchGet( 'wpsf-forcelogout' );
		if ( $sForceLogout == 6 ) {
			$oError->add( 'wpsf-forcelogout', _wpsf__('Your Two-Factor Authentication was un-verified or invalidated by a login from another location or browser.').'<br />'._wpsf__('Please login again.') );
		}
		return $oError;
	}

	/**
	 * @param $oUser
	 * @param $sUsername
	 * @param $sPassword
	 * @return mixed
	 */
	public function checkRemotePostLogin_Filter( $oUser, $sUsername, $sPassword ) {
		$oDp = $this->loadDataProcessor();
		$sHttpRef = $oDp->FetchServer( 'HTTP_REFERER' );

		if ( !empty( $sHttpRef ) ) {
			$aHttpRefererParts = parse_url( $sHttpRef );
			$aHomeUrlParts = parse_url( home_url() );

			if ( !empty( $aHttpRefererParts['host'] ) && !empty( $aHomeUrlParts['host'] ) && ( $aHttpRefererParts['host'] === $aHomeUrlParts['host'] ) ) {
				$this->doStatIncrement( 'login.remotepost.success' );
				return $oUser;
			}
		}

		$this->doStatIncrement( 'login.remotepost.fail' );
		$sAuditMessage = sprintf( _wpsf__( 'Blocked Remote Login Attempt by user "%s", where HTTP_REFERER was "%s".' ), $sUsername, $sHttpRef );
		$this->addToAuditEntry( $sAuditMessage, 3, 'login_protect_block_remote' );

		wp_die(
			_wpsf__( 'Sorry, you must login directly from within the site.' )
			.' '._wpsf__( 'Remote login is not supported.' )
			.'<br /><a href="http://icwp.io/4n" target="_blank">&rarr;'._wpsf__('More Info').'</a>'
		);
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Cooldown
	 */
	protected function getProcessorCooldown() {
		if ( !isset( $this->oProcessorCooldown ) ) {
			require_once( dirname(__FILE__).ICWP_DS.'loginprotect_cooldown.php' );
			$this->oProcessorCooldown = new ICWP_WPSF_Processor_LoginProtect_Cooldown( $this->getFeatureOptions() );
		}
		return $this->oProcessorCooldown;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_TwoFactorAuth
	 */
	protected function getProcessorTwoFactor() {
		if ( !isset( $this->oProcessorTwoFactor ) ) {
			require_once( dirname(__FILE__).ICWP_DS.'loginprotect_twofactorauth.php' );
			$this->oProcessorTwoFactor = new ICWP_WPSF_Processor_LoginProtect_TwoFactorAuth( $this->getFeatureOptions() );
		}
		return $this->oProcessorTwoFactor;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Gasp
	 */
	protected function getProcessorGasp() {
		if ( !isset( $this->oProcessorGasp ) ) {
			require_once( dirname(__FILE__).ICWP_DS.'loginprotect_gasp.php' );
			$this->oProcessorGasp = new ICWP_WPSF_Processor_LoginProtect_Gasp( $this->getFeatureOptions() );
		}
		return $this->oProcessorGasp;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_WpLogin
	 */
	protected function getProcessorWpLogin() {
		if ( !isset( $this->oProcessorWpLogin ) ) {
			require_once( dirname(__FILE__).ICWP_DS.'loginprotect_wplogin.php' );
			$this->oProcessorWpLogin = new ICWP_WPSF_Processor_LoginProtect_WpLogin( $this->getFeatureOptions() );
		}
		return $this->oProcessorWpLogin;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Yubikey
	 */
	protected function getProcessorYubikey() {
		if ( !isset( $this->oProcessorYubikey ) ) {
			require_once( dirname(__FILE__).ICWP_DS.'loginprotect_yubikey.php' );
			$this->oProcessorYubikey = new ICWP_WPSF_Processor_LoginProtect_Yubikey( $this->getFeatureOptions() );
		}
		return $this->oProcessorYubikey;
	}
}
endif;

if ( !class_exists( 'ICWP_WPSF_Processor_LoginProtect', false ) ):
	class ICWP_WPSF_Processor_LoginProtect extends ICWP_WPSF_Processor_LoginProtect_V6 { }
endif;