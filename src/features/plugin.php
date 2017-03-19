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

			if ( !$this->isTrackingPermissionSet() ) {
				add_action( 'wp_ajax_icwp_PluginTrackingPermission', array( $this, 'ajaxSetPluginTrackingPermission' ) );
			}
		}

		public function ajaxSetPluginTrackingPermission() {

			if ( self::getController()->getIsValidAdminArea() && $this->checkAjaxNonce() ) {
				$oDP = $this->loadDataProcessor();
				$this->setOpt( 'enable_tracking', $oDP->FetchGet( 'agree', 0 ) ? 'Y' : 'N' );
				$this->setOpt( 'tracking_permission_set_at', $oDP->time() );
				$this->sendAjaxResponse( true );
			}
			else {
				$this->sendAjaxResponse( false );
			}
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
		public function getTrackingLastSentAt() {
			$nTime = (int)$this->getOpt( 'tracking_last_sent_at', 0 );
			return ( $nTime < 0 ) ? 0 : $nTime;
		}

		/**
		 * @return string
		 */
		public function getLinkToTrackingDataDump() {
			return add_query_arg(
				array( 'shield_action' => 'dump_tracking_data' ),
				$this->loadWpFunctionsProcessor()->getUrl_WpAdmin()
			);
		}

		/**
		 * @return bool
		 */
		public function isTrackingEnabled() {
			return $this->getOptIs( 'enable_tracking', 'Y' );
		}

		/**
		 * @return bool
		 */
		public function isTrackingPermissionSet() {
			return !$this->getOptIs( 'tracking_permission_set_at', 0 );
		}

		/**
		 * @return int
		 */
		public function setTrackingLastSentAt() {
			return $this->setOpt( 'tracking_last_sent_at', $this->loadDataProcessor()->time() );
		}

		/**
		 * @return bool
		 */
		public function readyToSendTrackingData() {
			return ( ( $this->loadDataProcessor()->time() - $this->getTrackingLastSentAt() ) > WEEK_IN_SECONDS );
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
		 * This is the point where you would want to do any options verification
		 */
		protected function doPrePluginOptionsSave() {

			$nInstalledAt = $this->getPluginInstallationTime();
			if ( empty( $nInstalledAt ) || $nInstalledAt <= 0 ) {
				$this->setOpt( 'installation_time', $this->loadDataProcessor()->time() );
			}

			if ( $this->isTrackingEnabled() && !$this->isTrackingPermissionSet() ) {
				$this->setOpt( 'tracking_permission_set_at', $this->loadDataProcessor()->time() );
			}

			$this->setPluginInstallationId();
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
			return sha1(
				$this->getPluginInstallationTime()
				. $this->loadWpFunctionsProcessor()->getWpUrl()
				. $this->loadDbProcessor()->getPrefix()
			);
		}

		/**
		 * @param string $sId
		 * @return bool
		 */
		protected function isValidInstallId( $sId ) {
			return ( !empty( $sId ) && is_string( $sId ) && strlen( $sId ) == 40 );
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
			$aOptionsParams['title'] = $sTitle;
			$aOptionsParams['summary'] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : array();
			$aOptionsParams['title_short'] = $sTitleShort;
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
					$sName = _wpsf__( 'Enable Plugin Features' );
					$sSummary = _wpsf__( 'Global Plugin On/Off Switch' );
					$sDescription = sprintf( _wpsf__( 'Uncheck this option to disable all %s features.' ), self::getController()->getHumanName() );
					break;

				case 'enable_tracking' :
					$sName = sprintf( _wpsf__( 'Enable %s' ), _wpsf__( 'Information Gathering' ) );
					$sSummary = _wpsf__( 'Permit Anonymous Usage Information Gathering' );
					$sDescription = _wpsf__( 'Allows us to gather information on statistics and features in-use across our client installations.' )
						. ' ' . _wpsf__( 'This information is strictly anonymous and contains no personally, or otherwise, identifiable data.' )
						. '<br />' . sprintf( '<a href="%s" target="_blank">%s</a>', $this->getLinkToTrackingDataDump(), _wpsf__( 'Click to see the exact data that would be sent.' ) );
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

				case 'unique_installation_id' :
					$sName = _wpsf__( 'Installation ID' );
					$sSummary = _wpsf__( 'Unique Plugin Installation ID' );
					$sDescription = _wpsf__( 'Keep this ID private.' );
					break;

				case 'google_recaptcha_secret_key' :
					$sName = _wpsf__( 'reCAPTCHA Secret' );
					$sSummary = _wpsf__( 'Google reCAPTCHA Secret Key' );
					$sDescription = _wpsf__( 'Enter your Google reCAPTCHA secret key for use throughout the plugin.' );
					break;

				case 'google_recaptcha_site_key' :
					$sName = _wpsf__( 'reCAPTCHA Site Key' );
					$sSummary = _wpsf__( 'Google reCAPTCHA Site Key' );
					$sDescription = _wpsf__( 'Enter your Google reCAPTCHA site key for use throughout the plugin' );
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
			_wpsf__( 'WordPress Security Admin' );
			_wpsf__( 'Protect your security plugin not just your WordPress site' );
			_wpsf__( 'Security Admin' );
			_wpsf__( 'Audit Trail' );
			_wpsf__( 'Get a view on what happens on your site, when it happens' );
			_wpsf__( 'Audit Trail Viewer' );
			_wpsf__( 'Automatic Updates' );
			_wpsf__( 'Take back full control of WordPress automatic updates' );
			_wpsf__( 'Comments SPAM' );
			_wpsf__( 'Block comment SPAM and retain your privacy' );
			_wpsf__( 'Email' );
			_wpsf__( 'Firewall' );
			_wpsf__( 'Automatically block malicious URLs and data sent to your site' );
			_wpsf__( 'Hack Protection' );
			_wpsf__( 'HTTP Headers' );
			_wpsf__( 'Control HTTP Security Headers' );
			_wpsf__( 'IP Manager' );
			_wpsf__( 'Manage Visitor IP Address' );
			_wpsf__( 'Lockdown' );
			_wpsf__( 'Harden the more loosely controlled settings of your site' );
			_wpsf__( 'Login Protection' );
			_wpsf__( 'Block brute force attacks and secure user identities with Two-Factor Authentication' );
			_wpsf__( 'Dashboard' );
			_wpsf__( 'Overview of the plugin settings' );
			_wpsf__( 'Statistics' );
			_wpsf__( 'Summary of the main security actions taken by this plugin' );
			_wpsf__( 'Stats Viewer' );
			_wpsf__( 'Premium Support' );
			_wpsf__( 'Premium Plugin Support Centre' );
			_wpsf__( 'User Management' );
			_wpsf__( 'Get true user sessions and control account sharing, session duration and timeouts' );
		}
	}

endif;