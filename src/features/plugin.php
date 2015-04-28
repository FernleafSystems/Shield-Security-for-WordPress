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

if ( !class_exists( 'ICWP_WPSF_FeatureHandler_Plugin', false ) ):

	require_once( dirname(__FILE__).ICWP_DS.'base.php' );

	class ICWP_WPSF_FeatureHandler_Plugin extends ICWP_WPSF_FeatureHandler_Base {

		protected function doPostConstruction() {
			add_action( 'deactivate_plugin', array( $this, 'onWpHookDeactivatePlugin' ), 1, 1 );
			add_filter( $this->doPluginPrefix( 'report_email_address' ), array( $this, 'getPluginReportEmail' ) );
			add_filter( $this->doPluginPrefix( 'override_off' ), array( $this, 'fIsPluginGloballyEnabled' ) );
		}

		/**
		 * @param $bOverrideOff
		 *
		 * @return boolean
		 */
		public function fIsPluginGloballyEnabled( $bOverrideOff ) {
			return $bOverrideOff || !$this->getOptIs( 'global_enable_plugin_features', 'Y' );
		}

		/**
		 */
		public function doClearAdminFeedback() {
			$this->setOpt( 'feedback_admin_notice', array() );
		}

		/**
		 * @param string $sMessage
		 */
		public function doAddAdminFeedback( $sMessage ) {
			$aFeedback = $this->getOpt( 'feedback_admin_notice', array() );
			if ( !is_array( $aFeedback ) ) {
				$aFeedback = array();
			}
			$aFeedback[] = $sMessage;
			$this->setOpt( 'feedback_admin_notice', $aFeedback );
		}

		public function doExtraSubmitProcessing() {
			$this->doAddAdminFeedback( sprintf( _wpsf__( '%s Plugin options updated successfully.' ), $this->getController()->getHumanName() ) );
		}

		/**
		 * @return array
		 */
		public function getActivePluginFeatures() {
			$aActiveFeatures = $this->getOptionsVo()->getRawData_SingleOption( 'active_plugin_features' );
			$aPluginFeatures = array();
			if ( empty( $aActiveFeatures['value'] ) || !is_array( $aActiveFeatures['value'] ) ) {
				return $aPluginFeatures;
			}

			foreach( $aActiveFeatures['value'] as $nPosition => $aFeature ) {
				if ( isset( $aFeature['hidden'] ) && $aFeature['hidden'] ) {
					continue;
				}
				$aPluginFeatures[ $aFeature['slug'] ] = $aFeature;
			}
			return $aPluginFeatures;
		}

		/**
		 * @return mixed
		 */
		public function getIsMainFeatureEnabled() {
			return true;
		}

		/**
		 * @return array
		 */
		protected function buildFullIpWhitelist() {

			$aWhitelistFromOptions = $this->getIpWhitelistOption();

			$aIpWhitelist = array();
			$aOldWhitelists = apply_filters( 'icwp_simple_firewall_whitelist_ips', array() );
			if ( is_array( $aOldWhitelists ) ) {
				foreach( $aOldWhitelists as $mKey => $sValue ) {
					$aIpWhitelist[] = is_string( $mKey ) ? $mKey : $sValue;
				}
			}

			$aFullIpWhitelist = array_merge( $aWhitelistFromOptions, $aIpWhitelist );
			$aFinalPreChecking = array();
			foreach( $aFullIpWhitelist as $sItem ) {
				if ( strpos( $sItem, ' ' ) !== false ) {
					$aParts = explode( ' ', $sItem );
					$aFinalPreChecking = array_merge( $aFinalPreChecking, $aParts );
				}
				else {
					$aFinalPreChecking[] = $sItem;
				}
			}

			$aFinalUnique = array_unique( preg_replace( '#[^0-9a-zA-Z:.-]#', '', $aFinalPreChecking ) );

			$oDp = $this->loadDataProcessor();
			foreach( $aFinalUnique as $nPos => $sIp ) {
				if ( !$oDp->verifyIp( $sIp ) ) {
					unset( $aFinalUnique[$nPos] );
				}
			}

			$aDifference = array_diff( $aFinalUnique, $aWhitelistFromOptions );
			if ( !empty( $aDifference ) ) { // there's nothing new
				$this->setIpWhitelistOption( $aFinalUnique );
			}

			return $aFinalUnique;
		}

		/**
		 * @return array
		 */
		public function getIpWhitelistOption() {
			$aList = $this->getOpt( 'ip_whitelist', array() );
			if ( empty( $aList ) || !is_array( $aList ) ){
				$aList = array();
			}
			return $aList;
		}

		/**
		 * @param array $aList
		 *
		 * @return bool
		 */
		private function setIpWhitelistOption( $aList ) {
			if ( empty( $aList ) || !is_array( $aList ) ){
				$aList = array();
			}
			$this->setOpt( 'ip_whitelist', $aList );
			$this->doSaveByPassAdminProtection();
		}

		/**
		 * @param array $aSummaryData
		 * @return array
		 */
		public function filter_getFeatureSummaryData( $aSummaryData ) {
			return $aSummaryData;
		}

		/**
		 */
		public function displayFeatureConfigPage( ) {
			$aPluginSummaryData = apply_filters( $this->doPluginPrefix( 'get_feature_summary_data' ), array() );
			$aData = array(
				'aSummaryData'		=> $aPluginSummaryData
			);
			$this->display( $aData );
		}

		/**
		 * Hooked to 'deactivate_plugin' and can be used to interrupt the deactivation of this plugin.
		 *
		 * @param string $sPlugin
		 */
		public function onWpHookDeactivatePlugin( $sPlugin ) {
			if ( strpos( $this->getController()->getRootFile(), $sPlugin ) !== false ) {
				if ( !apply_filters( $this->doPluginPrefix( 'has_permission_to_submit' ), true ) ) {
					wp_die(
						_wpsf__( 'Sorry, you do not have permission to disable this plugin.')
						. _wpsf__( 'You need to authenticate first.' )
					);
				}
			}
		}

		/**
		 * @param $sEmail
		 * @return string
		 */
		public function getPluginReportEmail( $sEmail ) {
			$sReportEmail = $this->getOpt( 'block_send_email_address' );
			if ( !empty( $sReportEmail ) && is_email( $sReportEmail ) ) {
				$sEmail = $sReportEmail;
			}
			return $sEmail;
		}

		/**
		 * @param array $aOptionsParams
		 * @return array
		 * @throws Exception
		 */
		protected function loadStrings_SectionTitles( $aOptionsParams ) {

			$sSectionSlug = $aOptionsParams['section_slug'];
			switch( $aOptionsParams['section_slug'] ) {

				case 'section_global_security_options' :
					$sTitle = _wpsf__( 'Global Plugin Security Options' );
					break;

				case 'section_general_plugin_options' :
					$sTitle = _wpsf__( 'General Plugin Options' );
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

				case 'global_enable_plugin_features' :
					$sName = _wpsf__( 'Enable Features' );
					$sSummary = _wpsf__( 'Global Plugin On/Off Switch' );
					$sDescription = sprintf( _wpsf__( 'Uncheck this option to disable all WordPress Simple Firewall features.' ) );
					break;

				case 'ip_whitelist' :
					$sName = _wpsf__( 'IP Whitelist' );
					$sSummary = _wpsf__( 'IP Address White List' );
					$sDescription = sprintf( _wpsf__( 'Any IP addresses on this list will by-pass all Plugin Security Checking.' ) )
									.'<br />'.sprintf( _wpsf__( 'Your IP address is: %s' ), '<span class="code">'.( $this->loadDataProcessor()->getVisitorIpAddress() ).'</span>' );
					break;

				case 'block_send_email_address' :
					$sName = _wpsf__( 'Report Email' );
					$sSummary = _wpsf__( 'Where to send email reports' );
					$sDescription = sprintf( _wpsf__( 'If this is empty, it will default to the blog admin email address: %s' ), '<br /><strong>'.get_bloginfo('admin_email').'</strong>' );
					break;

				case 'enable_upgrade_admin_notice' :
					$sName = _wpsf__( 'In-Plugin Notices' );
					$sSummary = _wpsf__( 'Display Plugin Specific Notices' );
					$sDescription = _wpsf__( 'Disable this option to hide certain plugin admin notices about available updates and post-update notices.' );
					break;

				case 'display_plugin_badge' :
					$sName = _wpsf__( 'Show Plugin Badge' );
					$sSummary = _wpsf__( 'Display Plugin Badge On Your Site' );
					$sDescription = _wpsf__( 'Enabling this option helps support the plugin by spreading the word about it on your website.' )
						.' '._wpsf__('The plugin badge also lets visitors know your are taking your website security seriously.')
						.sprintf( '<br /><strong><a href="%s" target="_blank">%s</a></strong>', 'http://icwp.io/wpsf20', _wpsf__('Read this carefully before enabling this option.') );
					break;

				case 'delete_on_deactivate' :
					$sName = _wpsf__( 'Delete Plugin Settings' );
					$sSummary = _wpsf__( 'Delete All Plugin Settings Upon Plugin Deactivation' );
					$sDescription = _wpsf__( 'Careful: Removes all plugin options when you deactivate the plugin' );
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

			$nInstalledAt = $this->getOpt( 'installation_time' );
			if ( empty($nInstalledAt) || $nInstalledAt <= 0 ) {
				$this->setOpt( 'installation_time', time() );
			}

			// we only rebuild and verify the white list IP address in the admin area
			$this->buildFullIpWhitelist();
		}

		protected function updateHandler() {
			parent::updateHandler();
			if ( $this->getVersion() == '0.0' ) {
				return;
			}

			if ( version_compare( $this->getVersion(), '4.3.0', '<' ) ) { }
		}
	}

endif;