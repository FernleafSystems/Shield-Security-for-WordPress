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

if ( !class_exists('ICWP_WPSF_FeatureHandler_AdminAccessRestriction') ):

class ICWP_WPSF_FeatureHandler_AdminAccessRestriction extends ICWP_WPSF_FeatureHandler_Base {

	private $fHasPermissionToSubmit;

	/**
	 * @return string
	 */
	protected function getProcessorClassName() {
		return 'ICWP_WPSF_Processor_AdminAccessRestriction';
	}

	protected function doExecuteProcessor() {
		$sIp = $this->loadDataProcessor()->getVisitorIpAddress();
		$aIpWhitelist = apply_filters( $this->doPluginPrefix( 'ip_whitelist' ), array() );
		if ( is_array( $aIpWhitelist ) && ( in_array( $sIp, $aIpWhitelist )  ) ) {
			return;
		}
		parent::doExecuteProcessor();
	}

	/**
	 * @param bool $fHasPermission
	 * @return bool
	 */
	public function doCheckHasPermissionToSubmit( $fHasPermission = true ) {

		// We don't use setPermissionToSubmit() here because of timing with headers - we just for now manually
		// checking POST for the submission of the key and if it fits, we say "yes"
		if ( $this->checkAdminAccessKeySubmission() ) {
			$this->fHasPermissionToSubmit = true;
		}

		if ( isset( $this->fHasPermissionToSubmit ) ) {
			return $this->fHasPermissionToSubmit;
		}

		$oDp = $this->loadDataProcessor();

		$this->fHasPermissionToSubmit = $fHasPermission;
		if ( $this->getIsMainFeatureEnabled() )  {

			$sAccessKey = $this->getOpt( 'admin_access_key' );
			if ( !empty( $sAccessKey ) ) {
				$sHash = md5( $sAccessKey );
				$sCookieValue = $oDp->FetchCookie( $this->getAdminAccessKeyCookieName() );
				$this->fHasPermissionToSubmit = ( $sCookieValue === $sHash );
			}
		}
		return $this->fHasPermissionToSubmit;
	}

	/**
	 */
	protected function setAdminAccessCookie() {
		$sAccessKey = $this->getOpt( 'admin_access_key' );
		if ( !empty( $sAccessKey ) ) {
			$sValue = md5( $sAccessKey );
			$sTimeout = $this->getOpt( 'admin_access_timeout' ) * 60;
			$_COOKIE[ $this->getAdminAccessKeyCookieName() ] = $sValue;
			setcookie( $this->getAdminAccessKeyCookieName(), $sValue, time()+$sTimeout, COOKIEPATH, COOKIE_DOMAIN, false );
		}
	}

	/**
	 */
	protected function clearAdminAccessCookie() {
		unset( $_COOKIE[ $this->getAdminAccessKeyCookieName() ] );
		setcookie( $this->getAdminAccessKeyCookieName(), "", time()-3600, COOKIEPATH, COOKIE_DOMAIN, false );
	}

	/**
	 */
	protected function doExtraSubmitProcessing() {
		// We should only use setPermissionToSubmit() here, before any headers elsewhere are sent out.
		if ( $this->checkAdminAccessKeySubmission() ) {
			$this->setPermissionToSubmit( true );
//			wp_safe_redirect( network_admin_url() );
		}
	}

	/**
	 * @return string
	 */
	public function getAdminAccessKeyCookieName() {
		return $this->getOpt( 'admin_access_key_cookie_name' );
	}

	/**
	 * @param bool $fPermission
	 */
	protected function setPermissionToSubmit( $fPermission = false ) {
		if ( $fPermission ) {
			$this->setAdminAccessCookie();
		}
		else {
			$this->clearAdminAccessCookie();
		}
	}

	/**
	 * @return bool
	 */
	protected function checkAdminAccessKeySubmission() {
		$oDp = $this->loadDataProcessor();

		$sAccessKeyRequest = $oDp->FetchPost( $this->doPluginPrefix( 'admin_access_key_request', '_' ) );
		if ( empty( $sAccessKeyRequest ) ) {
			return false;
		}
		return $this->getOpt( 'admin_access_key' ) === md5( $sAccessKeyRequest );
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sSectionSlug = $aOptionsParams['section_slug'];
		switch( $aOptionsParams['section_slug'] ) {

			case 'section_enable_plugin_feature_admin_access_restriction' :
				$sTitle = sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), _wpsf__('Admin Access Restriction') );
				break;

			case 'section_admin_access_restriction_settings' :
				$sTitle = _wpsf__( 'Admin Access Restriction Settings' );
				break;

			default:
				throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams['section_title'] = $sTitle;
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_Options( $aOptionsParams ) {

		$sKey = $aOptionsParams['key'];
		switch( $sKey ) {

			case 'enable_admin_access_restriction' :
				$sName = sprintf( _wpsf__( 'Enable %s' ), _wpsf__('Admin Access') );
				$sSummary = _wpsf__( 'Enforce Admin Access Restriction' );
				$sDescription = _wpsf__( 'Enable this with great care and consideration. When this Access Key option is enabled, you must specify a key below and use it to gain access to this plugin.' );
				break;

			case 'admin_access_key' :
				$sName = _wpsf__( 'Admin Access Key' );
				$sSummary = _wpsf__( 'Provide/Update Admin Access Key' );
				$sDescription = sprintf( _wpsf__( 'Careful: %s' ), _wpsf__( 'If you forget this, you could potentially lock yourself out from using this plugin.' ) );
				break;

			case 'admin_access_timeout' :
				$sName = _wpsf__( 'Admin Access Timeout' );
				$sSummary = _wpsf__( 'Specify An Automatic Timeout Interval For Admin Access' );
				$sDescription = _wpsf__( 'This will automatically expire your WordPress Simple Firewall session. Does not apply until you enter the access key again.')
					.'<br />'.sprintf( _wpsf__( 'Default: %s minutes.' ), $this->getOptionsVo()->getOptDefault( 'admin_access_timeout' ) );
				break;

			default:
				throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams['name'] = $sName;
		$aOptionsParams['summary'] = $sSummary;
		$aOptionsParams['description'] = $sDescription;
		return $aOptionsParams;
	}

	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {

		if ( $this->getOpt( 'admin_access_timeout' ) < 1 ) {
			$this->getOptionsVo()->resetOptToDefault( 'admin_access_timeout' );
		}

		$sAccessKey = $this->getOpt( 'admin_access_key' );
		if ( empty( $sAccessKey ) ) {
			$this->setOpt( 'enable_admin_access_restriction', 'N' );
		}
	}

	protected function updateHandler() {
		parent::updateHandler();

		if ( $this->getVersion() == '0.0' ) {
			return;
		}

		if ( version_compare( $this->getVersion(), '3.0.0', '<' ) ) {
			$aAllOptions = apply_filters( $this->doPluginPrefix( 'aggregate_all_plugin_options' ), array() );
			$this->setOpt( 'enable_admin_access_restriction', $aAllOptions['enable_admin_access_restriction'] );
			$this->setOpt( 'admin_access_key', $aAllOptions['admin_access_key'] );
			$this->setOpt( 'admin_access_timeout', $aAllOptions['admin_access_timeout'] );
		}
	}
}
endif;