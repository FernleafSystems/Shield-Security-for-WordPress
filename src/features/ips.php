<?php

if ( class_exists( 'ICWP_WPSF_FeatureHandler_Ips', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_FeatureHandler_Ips extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	const LIST_MANUAL_WHITE = 'MW';
	const LIST_MANUAL_BLACK = 'MB';
	const LIST_AUTO_BLACK = 'AB';

	/**
	 * @return bool
	 */
	protected function isReadyToExecute() {
		$oIp = $this->loadIpService();
		return $oIp->isValidIp_PublicRange( $oIp->getRequestIp() ) && parent::isReadyToExecute();
	}

	/**
	 * @return array
	 */
	protected function getDisplayStrings() {
		return $this->loadDP()->mergeArraysRecursive(
			parent::getDisplayStrings(),
			array(
				'btn_actions'         => _wpsf__( 'Manage IP Lists' ),
				'btn_actions_summary' => _wpsf__( 'Add/Remove IPs' )
			)
		);
	}

	/**
	 * @return string
	 */
	public function getOptTransgressionLimit() {
		return $this->getOpt( 'transgression_limit' );
	}

	/**
	 * @return string
	 */
	public function getOptTracking404() {
		return $this->getOpt( 'track_404' );
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
	public function isAutoBlackListFeatureEnabled() {
		return ( $this->getOptTransgressionLimit() > 0 );
	}

	/**
	 * @param array $aData
	 */
	protected function displayModulePage( $aData = array() ) {
		add_thickbox();
		parent::displayModulePage( $this->getIpTableDisplayData() );
	}

	/**
	 * @return array
	 */
	protected function getContentCustomActionsData() {
		return $this->getIpTableDisplayData();
	}

	/**
	 * @return array
	 */
	protected function getIpTableDisplayData() { // Use new standard AJAX
		return array(
			'ajax' => $this->getAjaxDataSets(),
		);
	}

	/**
	 * @return array
	 */
	protected function getFormatedData_WhiteList() {
		/** @var ICWP_WPSF_Processor_Ips $oProcessor */
		$oProcessor = $this->getProcessor();
		return $this->formatEntriesForDisplay( $oProcessor->getWhitelistIpsData() );
	}

	/**
	 * @return array
	 */
	protected function getFormatedData_AutoBlackList() {
		/** @var ICWP_WPSF_Processor_Ips $oProcessor */
		$oProcessor = $this->getProcessor();
		return $this->formatEntriesForDisplay( $oProcessor->getAutoBlacklistIpsData() );
	}

	/**
	 * @param ICWP_WPSF_IpsEntryVO[] $aListData
	 * @return array
	 */
	protected function formatEntriesForDisplay( $aListData ) {
		$oWp = $this->loadWp();
		$oDp = $this->loadDP();

		foreach ( $aListData as $nKey => $oIp ) {
			$aItem = $oDp->convertStdClassToArray( $oIp->getRawData() );
			$sIp = $oIp->getIp();

			$aItem[ 'ip_link' ] =
				sprintf( '<a href="%s" target="_blank">%s</a>',
					(
					( $this->loadIpService()->getIpVersion( $sIp ) == 4 ) ?
						'http://whois.domaintools.com/'.$sIp
						: sprintf( 'http://whois.arin.net/rest/nets;q=%s?showDetails=true', $sIp )
					),
					$sIp
				);
			$aItem[ 'last_access_at' ] = $oWp->getTimeStringForDisplay( $oIp->getLastAccessAt() );
			$aItem[ 'created_at' ] = $oWp->getTimeStringForDisplay( $oIp->getCreatedAt() );

			$aListData[ $nKey ] = $aItem;
		}
		return $aListData;
	}

	/**
	 * @return string
	 */
	public function getIpListsTableName() {
		return $this->prefix( $this->getDef( 'ip_lists_table_name' ), '_' );
	}

	/**
	 * @premium
	 * @return bool
	 */
	public function is404Tracking() {
		return !$this->isOpt( 'track_404', 'disabled' );
	}

	/**
	 * @param array $aAjaxResponse
	 * @return array
	 */
	public function handleAuthAjax( $aAjaxResponse ) {

		if ( empty( $aAjaxResponse ) ) {
			switch ( $this->loadRequest()->request( 'exec' ) ) {

				case 'get_ip_list':
					$aAjaxResponse = $this->ajaxExec_GetIpList();
					break;

				case 'add_ip_white':
					$aAjaxResponse = $this->ajaxExec_AddIpToWhitelist();
					break;

				case 'ip_delete':
					$aAjaxResponse = $this->ajaxExec_IpDelete();
					break;

				case 'render_table_ip':
					$aAjaxResponse = $this->ajaxExec_BuildTableIps();
					break;

				default:
					break;
			}
		}
		return parent::handleAuthAjax( $aAjaxResponse );
	}

	/**
	 * @return array
	 */
	protected function ajaxExec_GetIpList() {
		return array(
			'success' => true,
			'html'    => $this->renderListTable( $this->loadRequest()->post( 'list', '' ) )
		);
	}

	public function ajaxExec_RemoveIpFromList() {
		$oReq = $this->loadRequest();
		/** @var ICWP_WPSF_Processor_Ips $oPro */
		$oPro = $this->getProcessor();
		$oPro->getQueryDeleter()
			 ->deleteIpOnList( $oReq->post( 'ip' ), $oReq->post( 'list' ) );

		return array(
			'success' => true,
			'html'    => $this->renderListTable( $oReq->post( 'list', '' ) ),
		);
	}

	protected function ajaxExec_IpDelete() {
		$oReq = $this->loadRequest();
		/** @var ICWP_WPSF_Processor_Ips $oProcessor */
		$oProcessor = $this->getProcessor();

		$bSuccess = false;
		$nId = $oReq->post( 'id', -1 );
		if ( !is_numeric( $nId ) || $nId < 0 ) {
			$sMessage = _wpsf__( "Invalid entry selected" );
		}
		else if ( $oProcessor->getQueryDeleter()->deleteById( $nId ) ) {
			$sMessage = _wpsf__( "IP address deleted" );
			$bSuccess = true;
		}
		else {
			$sMessage = _wpsf__( "IP address wasn't deleted from the list" );
		}

		return array(
			'success' => $bSuccess,
			'message' => $sMessage,
		);
	}

	protected function ajaxExec_AddIpToWhitelist() {
		$oReq = $this->loadRequest();
		/** @var ICWP_WPSF_Processor_Ips $oProcessor */
		$oProcessor = $this->getProcessor();

		$bSuccess = false;
		$sIp = $oReq->post( 'ip', '' );
		if ( empty( $sIp ) ) {
			$sMessage = _wpsf__( "IP address is empty" );
		}
		else if ( !$this->loadIpService()->isValidIp( $sIp ) ) {
			$sMessage = _wpsf__( "IP is not valid" );
		}
		else if ( $oProcessor->addIpToWhiteList( $sIp, $oReq->post( 'label', '' ) ) ) {
			$sMessage = _wpsf__( "IP address added" );
			$bSuccess = true;
		}
		else {
			$sMessage = _wpsf__( "IP address wasn't added to the list" );
		}
		return array(
			'success' => $bSuccess,
			'message' => $sMessage,
		);
	}

	/**
	 * @return array
	 */
	protected function getAjaxDataSets() {
		return array(
			'glist' => $this->getAjaxActionData( 'get_ip_list', true ),
			'alist' => $this->getAjaxActionData( 'add_ip_white', true ),
			'rlist' => $this->getAjaxActionData( 'remove_ip', true ),
		);
	}

	protected function renderListTable( $sListToRender ) {
		$aRenderData = array(
			'ajax'         => $this->getAjaxDataSets(),
			'list_id'      => $sListToRender,
			'bIsWhiteList' => $sListToRender == self::LIST_MANUAL_WHITE,
			'time_now'     => sprintf( '%s: %s', _wpsf__( 'now' ), $this->loadWp()->getTimeStringForDisplay() ),
			'sTableId'     => 'IpWhiteTable'.substr( md5( mt_rand() ), 0, 5 )
		);

		switch ( $sListToRender ) {

			// this is a hard-coded class... need to change this.  It was $oProcessor:: but 5.2 doesn't supprt.
			case self::LIST_MANUAL_WHITE :
				$aRenderData[ 'list_data' ] = $this->getFormatedData_WhiteList();
				break;

			case self::LIST_AUTO_BLACK :
				$aRenderData[ 'list_data' ] = $this->getFormatedData_AutoBlackList();
				break;

			default:
				$aRenderData[ 'list_data' ] = array();
				break;
		}

		return $this->renderTemplate( 'snippets/ip_list_table.php', $aRenderData );
	}

	/**
	 * @param string $sList
	 * @return array
	 */
	protected function ajaxExec_BuildTableIps() {
		parse_str( $this->loadRequest()->post( 'filters', '' ), $aFilters );
		$aParams = array_intersect_key(
			array_merge( $_POST, array_map( 'trim', $aFilters ) ),
			array_flip( array(
				'paged',
				'order',
				'orderby',
				'fList'
			) )
		);

		return array(
			'success' => true,
			'html'    => $this->renderTable( $aParams )
		);
	}

	/**
	 * @param array $aParams
	 * @return string
	 */
	protected function renderTable( $aParams = array() ) {

		// clean any params of nonsense
		foreach ( $aParams as $sKey => $sValue ) {
			if ( preg_match( '#[^a-z0-9_]#i', $sKey ) || preg_match( '#[^a-z0-9._-]#i', $sValue ) ) {
				unset( $aParams[ $sKey ] );
			}
		}
		$aParams = array_merge(
			array(
				'orderby' => 'created_at',
				'order'   => 'DESC',
				'paged'   => 1,
				'fList'   => '',
			),
			$aParams
		);
		$nPage = (int)$aParams[ 'paged' ];

		/** @var ICWP_WPSF_Processor_Ips $oPro */
		$oPro = $this->loadProcessor();
		/** @var ICWP_WPSF_IpsEntryVO[] $aEntries */
		$aEntries = $oPro->getQuerySelector()
						 ->setPage( $nPage )
						 ->setOrderBy( $aParams[ 'orderby' ], $aParams[ 'order' ] )
						 ->setLimit( 25 )
						 ->setResultsAsVo( true )
						 ->filterByList( $aParams[ 'fList' ] )
						 ->query();

		$oTable = $this->getTableRenderer( $aParams[ 'fList' ] )
					   ->setItemEntries( $this->formatEntriesForDisplay( $aEntries ) )
					   ->setPerPage( 25 )
					   ->prepare_items();
		ob_start();
		$oTable->display();
		return ob_get_clean();
	}

	/**
	 * @param string $sList
	 * @return IpWhiteTable
	 */
	protected function getTableRenderer( $sList = self::LIST_MANUAL_WHITE ) {
		if ( empty( $sList ) || $sList == self::LIST_MANUAL_WHITE ) {
			$this->requireCommonLib( 'Components/Tables/IpWhiteTable.php' );
			$sTable = new IpWhiteTable();
		}
		else {
			$this->requireCommonLib( 'Components/Tables/IpBlackTable.php' );
			$sTable = new IpBlackTable();
		}
		/** @var ICWP_WPSF_Processor_Ips $oPro */
		$oPro = $this->loadProcessor();
		$nCount = $oPro->getQuerySelector()->count();
		return $sTable->setTotalRecords( $nCount );
	}

	protected function doExtraSubmitProcessing() {
		if ( !in_array( $this->getOpt( 'auto_expire' ), array( 'minute', 'hour', 'day', 'week' ) ) ) {
			$this->getOptionsVo()->resetOptToDefault( 'auto_expire' );
		}

		$nLimit = $this->getOptTransgressionLimit();
		if ( !is_int( $nLimit ) || $nLimit < 0 ) {
			$this->getOptionsVo()->resetOptToDefault( 'transgression_limit' );
		}
	}

	/**
	 * @param string $sOptKey
	 * @return string
	 */
	public function getTextOptDefault( $sOptKey ) {

		switch ( $sOptKey ) {

			case 'text_loginfailed':
				$sText = sprintf( '%s: %s',
					_wpsf__( 'Warning' ),
					_wpsf__( 'Repeated login attempts that fail will result in a complete ban of your IP Address.' )
				);
				break;

			case 'text_remainingtrans':
				$sText = sprintf( '%s: %s',
					_wpsf__( 'Warning' ),
					_wpsf__( 'You have %s remaining transgression(s) against this site and then you will be black listed.' )
					.'<br/><strong>'._wpsf__( 'Seriously, stop repeating what you are doing or you will be locked out.' ).'</strong>'
				);
				break;

			default:
				$sText = parent::getTextOptDefault( $sOptKey );
				break;
		}
		return $sText;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		switch ( $aOptionsParams[ 'slug' ] ) {

			case 'section_enable_plugin_feature_ips' :
				$sTitle = sprintf( _wpsf__( 'Enable Module: %s' ), $this->getMainFeatureName() );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'The IP Manager allows you to whitelist, blacklist and configure auto-blacklist rules.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'IP Manager' ) ) )
					.'<br />'._wpsf__( 'You should also carefully review the automatic black list settings.' )
				);
				$sTitleShort = sprintf( _wpsf__( '%s/%s Module' ), _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
				break;

			case 'section_auto_black_list' :
				$sTitle = _wpsf__( 'Automatic IP Black List' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'The Automatic IP Black List system will block the IP addresses of naughty visitors after a specified number of transgressions.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'Automatic IP Black List' ) ) )
				);
				$sTitleShort = _wpsf__( 'Auto Black List' );
				break;

			case 'section_reqtracking' :
				$sTitle = _wpsf__( 'Bad Request Tracking' );
				$sTitleShort = _wpsf__( 'Request Tracking' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Track strange behaviour to determine whether visitors are legitimate.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( "These aren't security issues in their own right, but may indicate probing bots." ) )
				);
				break;

			default:
				list( $sTitle, $sTitleShort, $aSummary ) = $this->loadStrings_SectionTitlesDefaults( $aOptionsParams );
		}
		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : array();
		$aOptionsParams[ 'title_short' ] = $sTitleShort;
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_Options( $aOptionsParams ) {
		switch ( $aOptionsParams[ 'key' ] ) {

			case 'enable_ips' :
				$sName = sprintf( _wpsf__( 'Enable %s Module' ), $this->getMainFeatureName() );
				$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Module' ), $this->getMainFeatureName() );
				$sDescription = sprintf( _wpsf__( 'Un-Checking this option will completely disable the %s module.' ), $this->getMainFeatureName() );
				break;

			case 'transgression_limit' :
				$sName = _wpsf__( 'Transgression Limit' );
				$sSummary = _wpsf__( 'Visitor IP address will be Black Listed after X bad actions on your site' );
				$sDescription = sprintf( _wpsf__( 'A black mark is set against an IP address each time a visitor trips the defenses of the %s plugin.' ), self::getConn()
																																							  ->getHumanName() )
								.'<br />'._wpsf__( 'When the number of these transgressions exceeds specified limit, they are automatically blocked from accessing the site.' )
								.'<br />'.sprintf( _wpsf__( 'Set this to "0" to turn off the %s feature.' ), _wpsf__( 'Automatic IP Black List' ) );
				break;

			case 'auto_expire' :
				$sName = _wpsf__( 'Auto Block Expiration' );
				$sSummary = _wpsf__( 'After 1 "X" a black listed IP will be removed from the black list' );
				$sDescription = _wpsf__( 'Permanent and lengthy IP Black Lists are harmful to performance.' )
								.'<br />'._wpsf__( 'You should allow IP addresses on the black list to be eventually removed over time.' )
								.'<br />'._wpsf__( 'Shorter IP black lists are more efficient and a more intelligent use of an IP-based blocking system.' );
				break;

			case 'track_404' :
				$sName = _wpsf__( 'Track 404s' );
				$sSummary = _wpsf__( 'Use 404s As An Transgression' );
				$sDescription = _wpsf__( 'Repeated 404s may indicate a probing bot.' );
				break;

			case 'text_loginfailed' :
				$sName = _wpsf__( 'Login Failed' );
				$sSummary = _wpsf__( 'Visitor Triggers The IP Transgression System Through A Failed Login' );
				$sDescription = _wpsf__( 'This message is displayed if the visitor fails a login attempt.' );
				break;

			case 'text_remainingtrans' :
				$sName = _wpsf__( 'Remaining Transgressions' );
				$sSummary = _wpsf__( 'Visitor Triggers The IP Transgression System Through A Firewall Block' );
				$sDescription = _wpsf__( 'This message is displayed if the visitor triggered the IP Transgression system and reports how many transgressions remain before being blocked.' );
				break;

			default:
				throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $aOptionsParams[ 'key' ] ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}

	/**
	 * Hooked to the plugin's main plugin_shutdown action
	 */
	public function action_doFeatureShutdown() {
		if ( !$this->isPluginDeleting() ) {
			$this->addFilterIpsToWhiteList();
		}
		parent::action_doFeatureShutdown(); //save
	}

	/**
	 */
	protected function addFilterIpsToWhiteList() {
		$oSp = $this->loadServiceProviders();

		$aMwp = function_exists( 'mwp_init' ) ? array_flip( $oSp->getIps_ManageWp() ) : array();
		foreach ( $aMwp as $sIp => $n ) {
			$aMwp[ $sIp ] = 'ManageWP';
		}

		$aIps = apply_filters( 'icwp_simple_firewall_whitelist_ips', $aMwp );

		if ( !empty( $aIps ) && is_array( $aIps ) ) {
			/** @var ICWP_WPSF_Processor_Ips $oPro */
			$oPro = $this->getProcessor();

			$aWhiteIps = $oPro->getWhitelistIps();
			foreach ( $aIps as $sIP => $sLabel ) {
				if ( !in_array( $sIP, $aWhiteIps ) ) {
					$oPro->addIpToWhiteList( $sIP, $sLabel );
				}
			}
		}
	}

	protected function ensureFeatureEnabled() {
		// we prevent disabling of this feature if the white list isn't empty
		if ( !$this->isModuleEnabled() ) {
			/** @var ICWP_WPSF_Processor_Ips $oProcessor */
			$oProcessor = $this->getProcessor();
			if ( count( $oProcessor->getWhitelistIpsData() ) > 0 ) {
				$this->setIsMainFeatureEnabled( true );
				$this->setFlashAdminNotice(
					sprintf( _wpsf__( 'Sorry, the %s feature may not be disabled while there are IP addresses in the White List' ), $this->getMainFeatureName() )
				);
			}
		}
	}
}