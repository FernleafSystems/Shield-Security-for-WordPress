<?php

if ( !class_exists( 'ICWP_WPSF_FeatureHandler_Ips', false ) ):

	require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'base_wpsf.php' );

	class ICWP_WPSF_FeatureHandler_Ips extends ICWP_WPSF_FeatureHandler_BaseWpsf {

		/**
		 * @return string
		 */
		public function getTransgressionLimit() {
			return $this->getOpt( 'transgression_limit' );
		}

		/**
		 * @return int
		 */
		public function getAutoExpireTime() {
			return constant( strtoupper( $this->getOpt( 'auto_expire' ).'_IN_SECONDS' ) );
		}

		/**
		 * @return bool
		 */
		public function getIsAutoBlackListFeatureEnabled() {
			return ( $this->getTransgressionLimit() > 0 );
		}

		/**
		 * @return string
		 */
		public function getWhatIsMyServerIp() {

			$sThisServerIp = $this->getOpt( 'this_server_ip', '' );
			if ( $this->getIfLastCheckServerIpAtHasExpired() ) {
				$this->loadFileSystemProcessor(); // to ensure the necessary Class exits - we can clean this up later
				$sThisServerIp = $this->loadIpProcessor()->WhatIsMyIp();
				if ( is_string( $sThisServerIp ) ) {
					$this->setOpt( 'this_server_ip', $sThisServerIp );
				}
				// we always update so we don't forever check on every single page load
				$this->setOpt( 'this_server_ip_last_check_at', $this->loadDataProcessor()->time() );
			}
			return $sThisServerIp;
		}

		/**
		 * @return int
		 */
		public function getLastCheckServerIpAt() {
			return $this->getOpt( 'this_server_ip_last_check_at', 0 );
		}

		/**
		 * @return bool
		 */
		public function getIfLastCheckServerIpAtHasExpired() {
			return ( ( $this->loadDataProcessor()->time() - $this->getLastCheckServerIpAt() ) > DAY_IN_SECONDS );
		}

		/**
		 */
		public function displayFeatureConfigPage( ) {
			add_thickbox();
			$this->display( $this->getIpTableDisplayData(), 'feature-ips' );
		}

		/**
		 * @return array
		 */
		protected function getIpTableDisplayData() {
			return array( 'sAjaxNonce' => wp_create_nonce( 'fable_ip_list_action' ) );
		}

		/**
		 * @return array
		 */
		protected function getFormatedData_WhiteList() {
			/** @var ICWP_WPSF_Processor_Ips $oProcessor */
			$oProcessor = $this->getProcessor();
			return $this->formatIpListData( $oProcessor->getWhitelistData() );
		}
		/**
		 * @return array
		 */
		protected function getFormatedData_AutoBlackList() {
			/** @var ICWP_WPSF_Processor_Ips $oProcessor */
			$oProcessor = $this->getProcessor();
			return $this->formatIpListData( $oProcessor->getAutoBlacklistData() );
		}

		/**
		 * @param array $aListData
		 * @return array
		 */
		protected function formatIpListData( $aListData ) {
			$oWp = $this->loadWpFunctionsProcessor();

			foreach( $aListData as &$aListItem ) {
				$aListItem[ 'ip_link' ] =
					sprintf( '<a href="%s" target="_blank">%s</a>',
						(
						( $this->loadDataProcessor()->getIpAddressVersion( $aListItem['ip'] ) == 4 ) ?
							'http://whois.domaintools.com/'.$aListItem['ip']
							: sprintf( 'http://whois.arin.net/rest/nets;q=%s?showDetails=true', $aListItem['ip'] )
						),
						$aListItem['ip']
					);
				$aListItem[ 'last_access_at' ] = $oWp->getTimeStringForDisplay( $aListItem[ 'last_access_at' ] ) ;
				$aListItem[ 'created_at' ] = $oWp->getTimeStringForDisplay( $aListItem[ 'created_at' ] ) ;
			}
			return $aListData;
		}

		/**
		 * @return string
		 */
		public function getIpListsTableName() {
			return $this->doPluginPrefix( $this->getDefinition( 'ip_lists_table_name' ), '_' );
		}

		public function doPrePluginOptionsSave() {
			$sSetting = $this->getOpt( 'auto_expire' );
			if ( !in_array( $sSetting, array( 'minute', 'hour', 'day', 'week' ) ) ) {
				$this->getOptionsVo()->resetOptToDefault( 'auto_expire' );
			}

			$nLimit = $this->getTransgressionLimit();
			if ( !is_int( $nLimit ) || $nLimit < 0 ) {
				$this->getOptionsVo()->resetOptToDefault( 'transgression_limit' );
			}
		}

		protected function adminAjaxHandlers() {
			parent::adminAjaxHandlers();
			add_action( 'wp_ajax_icwp_wpsf_GetIpList', array( $this, 'ajaxGetIpList' ) );
			add_action( 'wp_ajax_icwp_wpsf_RemoveIpFromList', array( $this, 'ajaxRemoveIpFromList' ) );
			add_action( 'wp_ajax_icwp_wpsf_AddIpToWhiteList', array( $this, 'ajaxAddIpToWhiteList' ) );
		}

		public function ajaxGetIpList() {
			$bNonce = $this->checkAjaxNonce();
			if ( $bNonce ) {
				$sResponseData = array();
				$sResponseData['html'] = $this->renderListTable( $this->loadDataProcessor()->FetchPost( 'list', '' ) );
				$this->sendAjaxResponse( $bNonce, $sResponseData );
			}
		}

		public function ajaxRemoveIpFromList() {

			$bSuccess = $this->checkAjaxNonce();
			if ( $bSuccess ) {
				/** @var ICWP_WPSF_Processor_Ips $oProcessor */
				$oProcessor = $this->getProcessor();
				$sResponseData = array();

				$oDp = $this->loadDataProcessor();
				$oProcessor->removeIpFromList( $oDp->FetchPost( 'ip' ), $oDp->FetchPost( 'list' ) );

				$sResponseData['html'] = $this->renderListTable( $this->loadDataProcessor()->FetchPost( 'list', '' ) );
				$this->sendAjaxResponse( $bSuccess, $sResponseData );
			}
		}

		public function ajaxAddIpToWhiteList() {

			$bSuccess = $this->checkAjaxNonce();
			if ( $bSuccess ) {
				/** @var ICWP_WPSF_Processor_Ips $oProcessor */
				$oProcessor = $this->getProcessor();
				$sResponseData = array();

				$oDp = $this->loadDataProcessor();

				$sIp = $oDp->FetchPost( 'ip', '' );
				$sLabel = $oDp->FetchPost( 'label', '' );
				if ( !empty( $sIp ) ) {
					$mResult = $oProcessor->addIpToWhiteList( $sIp, $sLabel );
				}

				$sResponseData['html'] = $this->renderListTable( $this->loadDataProcessor()->FetchPost( 'list', '' ) );

//				if ( $mResult === false || $mResult < 1 ) {
//					$bSuccess = false;
//				}
				$this->sendAjaxResponse( $bSuccess, $sResponseData );
			}
		}

		/**
		 * Will send ajax error response immediately upon failure
		 * @return bool
		 */
		protected function checkAjaxNonce() {

			$sNonce = $this->loadDataProcessor()->FetchRequest( '_ajax_nonce', '' );
			if ( !apply_filters( $this->doPluginPrefix( 'has_permission_to_submit' ), true ) ) {
				$sMessage = _wpsf__( 'You need to authenticate with the plugin Admin Access Protection system.' );
			}
			else if ( empty( $sNonce ) ) {
				$sMessage = _wpsf__( 'Nonce security checking failed - the nonce value was empty.' );
			}
			else if ( wp_verify_nonce( $sNonce, 'fable_ip_list_action' ) === false ) {
				$sMessage = sprintf( _wpsf__( 'Nonce security checking failed - the nonce supplied was "%s".' ), $sNonce );
			}
			else {
				return true; // At this stage we passed the nonce check
			}

			// At this stage we haven't returned after success so we failed the nonce check
			$this->sendAjaxResponse( false, array( 'message' => $sMessage ) );
			return false; //unreachable
		}

		protected function renderListTable( $sListToRender ) {
			$oWp = $this->loadWpFunctionsProcessor();
			$aRenderData = array(
				'list_id' => $sListToRender,
				'bIsWhiteList' => $sListToRender == ICWP_WPSF_Processor_Ips::LIST_MANUAL_WHITE,
				'time_now' => sprintf( _wpsf__( 'now: %s' ), $oWp->getTimeStringForDisplay() ),
				'sAjaxNonce' => wp_create_nonce( 'fable_ip_list_action' ),
				'sTableId' => 'IpTable'.substr( md5( mt_rand() ), 0, 5 )
			);

			switch ( $sListToRender ) {

				// this is a hard-coded class... need to change this.  It was $oProcessor:: but 5.2 doesn't supprt.
				case ICWP_WPSF_Processor_Ips::LIST_MANUAL_WHITE :
					$aRenderData['list_data'] = $this->getFormatedData_WhiteList();
					break;

				case ICWP_WPSF_Processor_Ips::LIST_AUTO_BLACK :
					$aRenderData['list_data'] = $this->getFormatedData_AutoBlackList();
					break;

				default:
					$aRenderData['list_data'] = array();
					break;
			}

			return $this->renderTemplate( 'snippets/ip_list_table.php', $aRenderData );
		}

		/**
		 * @param array $aOptionsParams
		 * @return array
		 * @throws Exception
		 */
		protected function loadStrings_SectionTitles( $aOptionsParams ) {

			$sSectionSlug = $aOptionsParams['section_slug'];
			switch( $aOptionsParams['section_slug'] ) {

				case 'section_enable_plugin_feature_ips' :
					$sTitle = sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), $this->getMainFeatureName() );
					$aSummary = array(
						sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'The IP Manager allows you to whitelist, blacklist and configure auto-blacklist rules.' ) ),
						sprintf( _wpsf__( 'Recommendation - %s' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'IP Manager' ) ) )
						.'<br />'._wpsf__( 'You should also carefully review the automatic black list settings.' )
					);
					$sTitleShort = sprintf( '%s / %s', _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
					break;

				case 'section_auto_black_list' :
					$sTitle = _wpsf__( 'Automatic IP Black List' );
					$aSummary = array(
						sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'The Automatic IP Black List system will block the IP addresses of naughty visitors after a specified number of transgressions.' ) ),
						sprintf( _wpsf__( 'Recommendation - %s' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'Automatic IP Black List' ) ) )
					);
					$sTitleShort = _wpsf__( 'Auto Black List' );
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

				case 'enable_ips' :
					$sName = sprintf( _wpsf__( 'Enable %s' ), $this->getMainFeatureName() );
					$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Feature' ), $this->getMainFeatureName() );
					$sDescription = sprintf( _wpsf__( 'Checking/Un-Checking this option will completely turn on/off the whole %s feature.' ), $this->getMainFeatureName() );
					break;

				case 'transgression_limit' :
					$sName = _wpsf__( 'Transgression Limit' );
					$sSummary = _wpsf__( 'Visitor IP address will be Black Listed after X bad actions on your site' );
					$sDescription = sprintf( _wpsf__( 'A black mark is set against an IP address each time a visitor trips the defenses of the %s plugin.' ), $this->getController()->getHumanName() )
						.'<br />'. _wpsf__( 'When the number of these transgressions exceeds specified limit, they are automatically blocked from accessing the site.' )
						.'<br />'. sprintf( _wpsf__( 'Set this to "0" to turn off the %s feature.' ), _wpsf__( 'Automatic IP Black List' ) );
					break;

				case 'auto_expire' :
					$sName = _wpsf__( 'Auto Block Expiration' );
					$sSummary = _wpsf__( 'After 1 "X" a black listed IP will be removed from the black list' );
					$sDescription = _wpsf__( 'Permanent and lengthy IP Black Lists are harmful to performance.' )
						.'<br />'. _wpsf__( 'You should allow IP addresses on the black list to be eventually removed over time.' )
						.'<br />'. _wpsf__( 'Shorter IP black lists are more efficient and a more intelligent use of an IP-based blocking system.' );
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
		 * Hooked to the plugin's main plugin_shutdown action
		 */
		public function action_doFeatureShutdown() {
			if ( ! $this->getIsPluginDeleting() ) {
				$this->addFilterIpsToWhiteList();
				$this->ensureFeatureEnabled();
			}
			parent::action_doFeatureShutdown(); //save
		}

		protected function addFilterIpsToWhiteList() {
			$aIps = apply_filters( 'icwp_simple_firewall_whitelist_ips', array() );
			if ( !empty( $aIps ) && is_array( $aIps ) ) {
				/** @var ICWP_WPSF_Processor_Ips $oProcessor */
				$oProcessor = $this->getProcessor();
				foreach( $aIps as $sIP => $sLabel ) {
					$oProcessor->addIpToWhiteList( $sIP, $sLabel );
				}
			}
		}

		protected function ensureFeatureEnabled() {
			// we prevent disabling of this feature if the white list isn't empty
			if ( !$this->getIsMainFeatureEnabled() ) {
				/** @var ICWP_WPSF_Processor_Ips $oProcessor */
				$oProcessor = $this->getProcessor();
				if ( count( $oProcessor->getWhitelistData() ) > 0 ) {
					$this->setIsMainFeatureEnabled( true );
					$this->loadAdminNoticesProcessor()->addFlashMessage(
						sprintf( _wpsf__( 'Sorry, the %s feature may not be disabled while there are IP addresses in the White List' ), $this->getMainFeatureName() )
					);
				}
			}
		}
	}

endif;