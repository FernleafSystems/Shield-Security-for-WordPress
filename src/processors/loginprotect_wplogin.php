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

require_once( 'base.php' );

if ( !class_exists( 'ICWP_WPSF_Processor_LoginProtect_WpLogin', false ) ):

class ICWP_WPSF_Processor_LoginProtect_WpLogin extends ICWP_WPSF_Processor_Base {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();

		if ( !$oFO->getIsCustomLoginPathEnabled() || $this->doCheckForPluginConflict() ) {
			return false;
		}

		// Loads the wp-login.php is the correct URL is loaded
		add_action( 'init', array( $this, 'doBlockPossibleAutoRedirection' ) );

		// Loads the wp-login.php is the correct URL is loaded
		add_filter( 'wp_loaded', array( $this, 'aLoadWpLogin' ) );

		// kills the wp-login.php if it's being loaded by anything but the virtual URL
		add_action( 'login_init', array( $this, 'aLoginFormAction' ), 0 );

		// ensure that wp-login.php is never used in site urls or redirects
		add_filter( 'site_url', array( $this, 'fCheckForLoginPhp' ), 20, 2 );
		add_filter( 'network_site_url', array( $this, 'fCheckForLoginPhp' ), 20, 2 );
		add_filter( 'wp_redirect', array( $this, 'fCheckForLoginPhp' ), 20, 2 );

		return true;
	}

	/**
	 * @return bool - true if conflict exists
	 */
	protected function doCheckForPluginConflict() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();

		$oWp = $this->loadWpFunctionsProcessor();
		if ( $oWp->isMultisite() ) {

			$sNoticeMessage = sprintf(
				'<strong>%s</strong>: %s',
				_wpsf__( 'Warning' ),
				_wpsf__( 'Your login URL is unchanged because the Rename WP Login feature is not currently supported on WPMS.')
			);
			$this->doAddAdminNotice( $this->getAdminNoticeHtml( $sNoticeMessage, 'error', false ) );
			return true;
		}
		else if ( class_exists( 'Rename_WP_Login', false ) ) {

			$sNoticeMessage = sprintf(
				'<strong>%s</strong>: %s',
				_wpsf__( 'Warning' ),
				_wpsf__( 'Can not use the Rename WP Login feature because you have the "Rename WP Login" plugin installed and active.' )
			);
			$this->doAddAdminNotice( $this->getAdminNoticeHtml( $sNoticeMessage, 'error', false ) );
			return true;
		}
		else if ( !$oWp->getIsPermalinksEnabled() ) {

			$sNoticeMessage = sprintf(
				'<strong>%s</strong>: %s',
				_wpsf__( 'Warning' ),
				sprintf(
					_wpsf__( 'Can not use the Rename WP Login feature because you have not enabled %s.'),
					__('Permalinks')
				)
			);
			$this->doAddAdminNotice( $this->getAdminNoticeHtml( $sNoticeMessage, 'error', false ) );
			return true;
		}
		else if ( $oWp->getIsPermalinksEnabled() && $oWp->getDoesWpSlugExist( $this->getLoginPath() ) ) {

			$sNoticeMessage = sprintf(
				'<strong>%s</strong>: %s',
				_wpsf__( 'Warning' ),
				_wpsf__( 'Can not use the Rename WP Login feature because you have chosen a path that is already reserved on your WordPress site.' )
			);
			$this->doAddAdminNotice( $this->getAdminNoticeHtml( $sNoticeMessage, 'error', false ) );
			return true;
		}
		return false;
	}

	/**
	 */
	public function doBlockPossibleAutoRedirection(){

		$fDoBlock = false;
		if ( is_admin() && !is_user_logged_in() && !defined( 'DOING_AJAX' ) ) {
			$fDoBlock = true;
		}

		$aRequestParts = $this->loadDataProcessor()->getRequestUriParts();
		$sPath = trim( $aRequestParts[ 'path' ], '/' );
		$aPossiblePaths = array(
			trim( home_url( 'wp-login.php', 'relative' ), '/' ),
			trim( site_url( 'wp-login.php', 'relative' ), '/' ),
			trim( home_url( 'login', 'relative' ), '/' ),
			trim( site_url( 'login', 'relative' ), '/' )
		);

		if ( in_array( $sPath, $aPossiblePaths ) ) {
			$fDoBlock = true;
		}

		if ( $fDoBlock ) {
			$this->do404();
		}
	}

	/**
	 * @return string
	 */
	protected function getLoginPath() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();
		return $oFO->getCustomLoginPath();
	}

	/**
	 * @param string $sUrl
	 * @param string $sPath
	 * @return string
	 */
	public function fCheckForLoginPhp( $sUrl, $sPath ) {
		if ( strpos( $sUrl, 'wp-login.php' ) !== false ) {

			$sLoginUrl = home_url( $this->getLoginPath() );
			$aQueryArgs = explode( '?', $sUrl );
			if ( !empty( $aQueryArgs[1] ) ) {
				parse_str( $aQueryArgs[1], $aNewQueryArgs );
				$sLoginUrl = add_query_arg( $aNewQueryArgs, $sLoginUrl );
			}
			return $sLoginUrl;
		}
		return $sUrl;
	}

	protected function isRealLogin() {
		$aRequestParts = $this->loadDataProcessor()->getRequestUriParts();
		$sLoginPath = $this->getLoginPath();
		$sRequestPath = $aRequestParts[ 'path' ];
		return trim( $sLoginPath, '/' ) === trim( $sRequestPath, '/' ) ;
	}

	/**
	 * @return string|void
	 */
	public function aLoadWpLogin() {
		if ( $this->isRealLogin() ) {
//			wp_login_form();
			@require_once( ABSPATH . 'wp-login.php' );
			die();
		}
	}

	public function aLoginFormAction() {
		if ( !$this->isRealLogin() ) {
			$this->do404();
			die();
		}
	}

	protected function do404() {
		$oDp = $this->loadDataProcessor();
		$sRequestUrl = $oDp->FetchServer( 'REQUEST_URI' );
		$oDp->doSendApache404(
			$sRequestUrl,
			home_url()
		);
	}
}
endif;