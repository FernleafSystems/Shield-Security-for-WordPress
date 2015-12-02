<?php

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

		public function doExtraSubmitProcessing() {
			$this->loadAdminNoticesProcessor()->addFlashMessage( sprintf( _wpsf__( '%s Plugin options updated successfully.' ), $this->getController()->getHumanName() ) );
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
		public function getIpWhitelistOption() {
			$aList = $this->getOpt( 'ip_whitelist', array() );
			if ( empty( $aList ) || !is_array( $aList ) ){
				$aList = array();
			}
			return $aList;
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
					$this->loadWpFunctionsProcessor()->wpDie(
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
					$sTitleShort = _wpsf__( 'Global Options' );
					break;

				case 'section_general_plugin_options' :
					$sTitle = _wpsf__( 'General Plugin Options' );
					$sTitleShort = _wpsf__( 'General Options' );
					break;

				default:
					throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
			}
			$aOptionsParams['section_title'] = $sTitle;
			$aOptionsParams['section_summary'] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : array();
			$aOptionsParams['section_title_short'] = $sTitleShort;
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
					$sDescription = sprintf( _wpsf__( 'Uncheck this option to disable all %s features.' ), $this->getController()->getHumanName() );
					break;

				case 'ip_whitelist' :
					$sName = _wpsf__( 'IP Whitelist' );
					$sSummary = _wpsf__( 'IP Address White List' );
					$sDescription = sprintf( _wpsf__( 'Any IP addresses on this list will by-pass all Plugin Security Checking.' ) )
									.'<br />'.sprintf( _wpsf__( 'Your IP address is: %s' ), '<span class="code">'.( $this->loadDataProcessor()->getVisitorIpAddress() ).'</span>' )
									.'<br /><strong>This option is deprecated - Please use the IP Manager to set white list IPs.</strong>';
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

			$nInstalledAt = $this->getPluginInstallationTime();
			if ( empty( $nInstalledAt ) || $nInstalledAt <= 0 ) {
				$this->setOpt( 'installation_time', $this->loadDataProcessor()->time() );
			}

			$sUniqueId = $this->getPluginInstallationId();
			if ( empty( $sUniqueId ) || !is_string( $sUniqueId ) || strlen( $sUniqueId ) != 32 ) {
				$this->setPluginInstallationId();
			}
		}

		/**
		 * @return string
		 */
		public function getPluginInstallationId() {
			return $this->getOpt( 'unique_installation_id', '' );
		}

		/**
		 * @return string
		 */
		public function getPluginInstallationTime() {
			return $this->getOpt( 'installation_time', 0 );
		}

		/**
		 * @param string $sNewId - leave empty to reset
		 * @return bool
		 */
		public function setPluginInstallationId( $sNewId = null ) {
			if ( empty( $sNewId ) ) {
				$sNewId = md5( $this->getOpt( 'installation_time' ) . $this->loadWpFunctionsProcessor()->getHomeUrl() . rand( 0, 1000 ) );
			}
			return $this->setOpt( 'unique_installation_id', $sNewId );
		}

		protected function updateHandler() {
			parent::updateHandler();
			if ( $this->getVersion() == '0.0' ) {
				return;
			}

			// we need to update the meta keys for notices.
			if ( is_admin() && version_compare( $this->getVersion(), '4.10.4', '<=' ) ) {
				$aOldMetaMap = array(
					'plugin_translation_notice' => 'translate-plugin',
					'php53_version_warning' => 'php53-version-warning',
					'plugin_mailing_list_signup' => 'plugin-mailing-list-signup'
				);
				$oWpAdminNotices = $this->loadAdminNoticesProcessor();
				$oWpUsers = $this->loadWpUsersProcessor();
				foreach( $aOldMetaMap as $sOldMeta => $sNewId ) {
					if ( $oWpUsers->getUserMeta( $this->prefixOptionKey( $sOldMeta ) ) == 'Y' ) {
						$oWpAdminNotices->setAdminNoticeAsDismissed( array( 'id' => $sNewId ) );
					}
					$oWpUsers->deleteUserMeta( $this->prefixOptionKey( $sOldMeta ) );
				}
			}
		}
	}

endif;