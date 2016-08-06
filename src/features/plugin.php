<?php

if ( !class_exists( 'ICWP_WPSF_FeatureHandler_Plugin', false ) ):

	require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'base_wpsf.php' );

	class ICWP_WPSF_FeatureHandler_Plugin extends ICWP_WPSF_FeatureHandler_BaseWpsf {

		protected function doPostConstruction() {
			add_action( 'deactivate_plugin', array( $this, 'onWpHookDeactivatePlugin' ), 1, 1 );
			add_filter( $this->doPluginPrefix( 'report_email_address' ), array( $this, 'getPluginReportEmail' ) );
			add_filter( $this->doPluginPrefix( 'globally_disabled' ), array( $this, 'filter_IsPluginGloballyDisabled' ) );
			add_filter( $this->doPluginPrefix( 'google_recaptcha_secret_key' ), array( $this, 'supplyGoogleRecaptchaSecretKey' ) );
			add_filter( $this->doPluginPrefix( 'google_recaptcha_site_key' ), array( $this, 'supplyGoogleRecaptchaSiteKey' ) );
		}

		/**
		 * @param string $sKey
		 * @return string
		 */
		public function supplyGoogleRecaptchaSecretKey( $sKey ) {
			return $this->getOpt( 'google_recaptcha_secret_key', $sKey );
		}

		/**
		 * @param string $sKey
		 * @return string
		 */
		public function supplyGoogleRecaptchaSiteKey( $sKey ) {
			return $this->getOpt( 'google_recaptcha_site_key', $sKey );
		}

		/**
		 * @param boolean $bGloballyDisabled
		 * @return boolean
		 */
		public function filter_IsPluginGloballyDisabled( $bGloballyDisabled ) {
			return $bGloballyDisabled || !$this->getOptIs( 'global_enable_plugin_features', 'Y' );
		}

		public function doExtraSubmitProcessing() {
			$this->loadAdminNoticesProcessor()->addFlashMessage( sprintf( _wpsf__( '%s Plugin options updated successfully.' ), self::getController()->getHumanName() ) );
		}

		/**
		 * @return array
		 */
		public function getActivePluginFeatures() {
			$aActiveFeatures = $this->getDefinition( 'active_plugin_features' );
			
			$aPluginFeatures = array();
			if ( empty( $aActiveFeatures ) || !is_array( $aActiveFeatures ) ) {
				return $aPluginFeatures;
			}

			foreach( $aActiveFeatures as $nPosition => $aFeature ) {
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
		 * Hooked to 'deactivate_plugin' and can be used to interrupt the deactivation of this plugin.
		 *
		 * @param string $sPlugin
		 */
		public function onWpHookDeactivatePlugin( $sPlugin ) {
			$oCon = self::getController();
			if ( strpos( $oCon->getRootFile(), $sPlugin ) !== false ) {
				if ( !$oCon->getHasPermissionToManage() ) {
					$this->loadWpFunctionsProcessor()->wpDie(
						_wpsf__( 'Sorry, you do not have permission to disable this plugin.')
						. _wpsf__( 'You need to authenticate first.' )
					);
				}
			}
		}

		/**
		 * @return string
		 */
		public function getTrackingCronName() {
			return $this->doPluginPrefix( $this->getDefinition( 'tracking_cron_handle' ) );
		}

		/**
		 * @return int
		 */
		public function getLastTrackingSentAt() {
			return $this->getOpt( 'last_tracking_sent_at' );
		}

		/**
		 * @return int
		 */
		public function updateLastTrackingSentAt() {
			return $this->setOpt( 'last_tracking_sent_at', $this->loadDataProcessor()->time() );
		}

		/**
		 * @return int
		 */
		public function getTrackingEnabled() {
			return $this->getOptIs( 'enable_tracking', 'Y' );
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

				case 'section_third_party_google' :
					$sTitle = _wpsf__( 'Google' );
					$sTitleShort = _wpsf__( 'Google' );
					break;

				case 'section_third_party_duo' :
					$sTitle = _wpsf__( 'Duo Security' );
					$sTitleShort = _wpsf__( 'Duo Security' );
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
					$sDescription = sprintf( _wpsf__( 'Uncheck this option to disable all %s features.' ), self::getController()->getHumanName() );
					break;

				case 'enable_tracking' :
					$sName = sprintf( _wpsf__( 'Enable %s' ), _wpsf__( 'Tracking' ) );
					$sSummary = _wpsf__( 'Permit Anonymous Usage Information Gathering' );
					$sDescription = _wpsf__( 'Allows us to gather information on global statistics and features in-use across our client installations.' )
						. ' ' . _wpsf__( 'This information is strictly anonymous and contains no personally, or otherwise, identifiable data.' );
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

				case 'unique_installation_id' :
					$sName = _wpsf__( 'Installation ID' );
					$sSummary = _wpsf__( 'Unique Plugin Installation ID' );
					$sDescription = _wpsf__( 'Keep this ID private.' );
					break;

				case 'delete_on_deactivate' :
					$sName = _wpsf__( 'Delete Plugin Settings' );
					$sSummary = _wpsf__( 'Delete All Plugin Settings Upon Plugin Deactivation' );
					$sDescription = _wpsf__( 'Careful: Removes all plugin options when you deactivate the plugin' );
					break;

				case 'google_recaptcha_secret_key' :
					$sName = _wpsf__( 'reCAPTCHA Secret' );
					$sSummary = _wpsf__( 'Google reCAPTCHA Secret Key' );
					$sDescription = _wpsf__( 'Enter your Google reCAPTCHA site key for use throughout the plugin.' );
					break;

				case 'google_recaptcha_site_key' :
					$sName = _wpsf__( 'reCAPTCHA Site Key' );
					$sSummary = _wpsf__( 'Google reCAPTCHA Site Key' );
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

			if ( $this->getIsUpgrading() ) {
				$this->setPluginInstallationId();
			}
		}

		/**
		 * Ensure we always a valid installation ID.
		 * @return string
		 */
		public function getPluginInstallationId() {
			$sId = $this->getOpt( 'unique_installation_id', '' );
			if ( !$this->isValidInstallId( $sId ) ) {
				$sId = $this->setPluginInstallationId();
			}
			return $sId;
		}

		/**
		 * @param string $sNewId - leave empty to reset if the current isn't valid
		 * @return string
		 */
		protected function setPluginInstallationId( $sNewId = null ) {
			// only reset if it's not of the correct type
			if ( !$this->isValidInstallId( $sNewId ) ) {
				$sNewId = $this->genInstallId();
			}
			$this->setOpt( 'unique_installation_id', $sNewId );
			return $sNewId;
		}

		/**
		 * @return string
		 */
		protected function genInstallId() {
			return sha1( $this->getPluginInstallationTime() . $this->loadWpFunctionsProcessor()->getWpUrl() );
		}

		/**
		 * @param string $sId
		 * @return bool
		 */
		protected function isValidInstallId( $sId ) {
			return ( !empty( $sId ) && is_string( $sId ) && strlen( $sId ) == 40 );
		}

		/**
		 * Kept just in-case.
		 */
		protected function old_translations() {
			_wpsf__( 'IP Whitelist' );
			_wpsf__( 'IP Address White List' );
			_wpsf__( 'Any IP addresses on this list will by-pass all Plugin Security Checking.' );
			_wpsf__( 'Your IP address is: %s' );
			_wpsf__( 'Choose IP Addresses To Blacklist' );
			_wpsf__( 'Recommendation - %s' );
			_wpsf__( 'Blacklist' );
			_wpsf__( 'Logging' );
			_wpsf__( 'User "%s" was forcefully logged out as they were not verified by either cookie or IP address (or both).' );
			_wpsf__( 'User "%s" was found to be un-verified at the given IP Address: "%s".' );
			_wpsf__( 'Cookie' );
			_wpsf__( 'IP Address' );
			_wpsf__( 'IP' );
			_wpsf__( 'This will restrict all user login sessions to a single browser. Use this if your users have dynamic IP addresses.' );
			_wpsf__( 'All users will be required to authenticate their login by email-based two-factor authentication, when logging in from a new IP address' );
			_wpsf__( '2-Factor Auth' );
			_wpsf__( 'Include Logged-In Users' );
			_wpsf__( 'You may also enable GASP for logged in users' );
			_wpsf__( 'Since logged-in users would be expected to be vetted already, this is off by default.' );
		}
	}

endif;