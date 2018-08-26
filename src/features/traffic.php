<?php

if ( class_exists( 'ICWP_WPSF_FeatureHandler_Traffic', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_FeatureHandler_Traffic extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	protected function doPostConstruction() {
		parent::doPostConstruction();
		$this->loadAutoload();
	}

	/**
	 * Hooked to the plugin's main plugin_shutdown action
	 */
	public function action_doFeatureShutdown() {
		if ( $this->isAutoDisable() && $this->loadDP()->time() - $this->getAutoDisableAt() > 0 ) {
			$this->setOpt( 'auto_disable', 'N' )
				 ->setOpt( 'autodisable_at', 0 )
				 ->setIsMainFeatureEnabled( false );
		}
		parent::action_doFeatureShutdown();
	}

	/**
	 * We clean the database after saving.
	 */
	protected function doPrePluginOptionsSave() {
		/** @var ICWP_WPSF_Processor_Traffic $oPro */
		$oPro = $this->getProcessor();
		$oPro->getProcessorLogger()
			 ->cleanupDatabase();
	}

	/**
	 * We clean the database after saving.
	 */
	protected function doExtraSubmitProcessing() {
		/** @var ICWP_WPSF_Processor_Traffic $oPro */
		$oPro = $this->getProcessor();
		$oPro->getProcessorLogger()
			 ->cleanupDatabase();

		$this->setOpt( 'autodisable_at', $this->isAutoDisable() ? $this->loadDP()->time() + WEEK_IN_SECONDS : 0 );
	}

	/**
	 * @return bool
	 */
	protected function isReadyToExecute() {
		$oIp = $this->loadIpService();
		return parent::isReadyToExecute()
			   && $oIp->isValidIp_PublicRange( $oIp->getRequestIp() )
			   && !$this->isVisitorWhitelisted();
	}

	/**
	 * @param string $sSectionSlug
	 * @return array
	 */
	protected function getSectionWarnings( $sSectionSlug ) {
		$aWarnings = array();

		if ( !$this->isPremium() ) {
			$aWarnings[] = sprintf( _wpsf__( '%s is a Pro-only feature.' ), _wpsf__( 'Traffic Watch' ) );
		}
		else {
			$oIp = $this->loadIpService();
			if ( !$this->loadIpService()->isValidIp_PublicRange( $oIp->getRequestIp() ) ) {
				$aWarnings[] = _wpsf__( 'Traffic Watcher will not run because visitor IP address detection is not correctly configured.' );
			}
		}

		return $aWarnings;
	}

	/**
	 * @return int
	 */
	public function getAutoCleanDays() {
		return (int)$this->getOpt( 'auto_clean' );
	}

	/**
	 * @return array
	 */
	protected function getExclusions() {
		$aEx = $this->getOpt( 'type_exclusions' );
		return is_array( $aEx ) ? $aEx : array();
	}

	/**
	 * @return int
	 */
	public function getMaxEntries() {
		return (int)$this->getOpt( 'max_entries' );
	}

	/**
	 * @return int
	 */
	public function getAutoDisableAt() {
		return (int)$this->getOpt( 'autodisable_at' );
	}

	/**
	 * @return string
	 */
	protected function getAutoDisableTimestamp() {
		return $this->loadWp()->getTimeStampForDisplay( $this->getAutoDisableAt() );
	}

	/**
	 * @return string
	 */
	public function getTrafficTableName() {
		return $this->prefix( $this->getDef( 'traffic_table_name' ), '_' );
	}

	/**
	 * @return bool
	 */
	public function isAutoDisable() {
		return $this->getOptIs( 'auto_disable', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isIncluded_Ajax() {
		return !in_array( 'ajax', $this->getExclusions() );
	}

	/**
	 * @return bool
	 */
	public function isIncluded_Cron() {
		return !in_array( 'cron', $this->getExclusions() );
	}

	/**
	 * @return bool
	 */
	public function isIncluded_LoggedInUser() {
		return !in_array( 'logged_in', $this->getExclusions() );
	}

	/**
	 * @return bool
	 */
	public function isIncluded_Search() {
		return !in_array( 'search', $this->getExclusions() );
	}

	/**
	 * @return bool
	 */
	public function isIncluded_Uptime() {
		return !in_array( 'uptime', $this->getExclusions() );
	}

	/**
	 * @return bool
	 */
	public function isLogUsers() {
		return $this->isIncluded_LoggedInUser();
	}

	/**
	 * @return array
	 */
	protected function getContentCustomActionsData() {
		return array(
			'sYourIp'           => $this->loadIpService()->getRequestIp(),
			'sLiveTrafficTable' => $this->renderLiveTrafficTable(),
			'sTitle'            => _wpsf__( 'Traffic Watch Viewer' ),
			'ajax'              => array(
				'render_table' => $this->getAjaxActionData( 'render_traffic_table', true )
			)
		);
	}

	/**
	 * @param array $aAjaxResponse
	 * @return array
	 */
	public function handleAuthAjax( $aAjaxResponse ) {

		if ( empty( $aAjaxResponse ) ) {
			switch ( $this->loadDP()->request( 'exec' ) ) {

				case 'render_traffic_table':
					$aAjaxResponse = $this->ajaxExec_RenderAuditTable();
					break;

				default:
					break;
			}
		}
		return parent::handleAuthAjax( $aAjaxResponse );
	}

	protected function ajaxExec_RenderAuditTable() {
		$oDP = $this->loadDP();
		parse_str( $oDP->post( 'filters', '' ), $aFilters );
		$aParams = array_intersect_key(
			array_merge( $_POST, array_map( 'trim', $aFilters ) ),
			array_flip( array(
				'paged',
				'order',
				'orderby',
				'fIp',
				'fPath',
				'fResponse',
				'fUsername',
				'fLoggedIn',
				'fTransgression',
				'fExludeYou'
			) )
		);
		return array(
			'success' => true,
			'html'    => $this->renderLiveTrafficTable( $aParams )
		);
	}

	/**
	 * @param string $sContext
	 * @param array  $aParams
	 * @return string
	 */
	protected function renderLiveTrafficTable( $aParams = array() ) {

		// clean any params of nonsense
		foreach ( $aParams as $sKey => $sValue ) {
			if ( preg_match( '#[^a-z0-9_]#i', $sKey ) || preg_match( '#[^a-z0-9._-]#i', $sValue ) ) {
				unset( $aParams[ $sKey ] );
			}
		}
		$aParams = array_merge(
			array(
				'orderby'        => 'created_at',
				'order'          => 'DESC',
				'paged'          => 1,
				'fIp'            => '',
				'fUsername'      => '',
				'fLoggedIn'      => -1,
				'fPath'          => '',
				'fTransgression' => -1,
				'fResponse'      => '',
				'fExludeYou'     => '',
			),
			$aParams
		);
		$nPage = (int)$aParams[ 'paged' ];

		/** @var ICWP_WPSF_Processor_Traffic $oTrafficPro */
		$oTrafficPro = $this->loadProcessor();
		$oSelector = $oTrafficPro->getProcessorLogger()
								 ->getTrafficEntrySelector()
								 ->setPage( $nPage )
								 ->setOrderBy( $aParams[ 'orderby' ], $aParams[ 'order' ] )
								 ->setLimit( $this->getDefaultPerPage() )
								 ->setResultsAsVo( true );
		// Filters
		{
			$oIp = $this->loadIpService();
			// If an IP is specified, it takes priority
			if ( $oIp->isValidIp( $aParams[ 'fIp' ] ) ) {
				$oSelector->filterByIp( inet_pton( $aParams[ 'fIp' ] ) );
			}
			else if ( $aParams[ 'fExludeYou' ] == 'Y' ) {
				$oSelector->filterByNotIp( inet_pton( $oIp->getRequestIp() ) );
			}

			// if username is provided, this takes priority over "logged-in" (even if it's invalid)
			if ( !empty( $aParams[ 'fUsername' ] ) ) {
				$oUser = $this->loadWpUsers()->getUserByUsername( $aParams[ 'fUsername' ] );
				if ( !empty( $oUser ) ) {
					$oSelector->filterByUserId( $oUser->ID );
				}
			}
			else if ( $aParams[ 'fLoggedIn' ] >= 0 ) {
				$oSelector->filterByIsLoggedIn( $aParams[ 'fLoggedIn' ] );
			}

			if ( $aParams[ 'fTransgression' ] >= 0 ) {
				$oSelector->filterByIsTransgression( $aParams[ 'fTransgression' ] );
			}

			$oSelector->filterByPathContains( $aParams[ 'fPath' ] );
			$oSelector->filterByResponseCode( $aParams[ 'fResponse' ] );
		}

		$aEntries = $oSelector->query();

		$oTable = $this->getTableRenderer()
					   ->setItemEntries( $this->formatEntriesForDisplay( $aEntries ) )
					   ->setPerPage( $this->getDefaultPerPage() )
					   ->prepare_items();
		ob_start();
		$oTable->display();
		return ob_get_clean();
	}

	/**
	 * Move to table
	 * @param ICWP_WPSF_TrafficEntryVO[] $aEntries
	 * @return array
	 */
	public function formatEntriesForDisplay( $aEntries ) {

		if ( is_array( $aEntries ) ) {
			$oCon = $this->getController();
			$oWpUsers = $this->loadWpUsers();
			$oGeo = $this->loadGeoIp2();
			$sYou = $this->loadIpService()->getRequestIp();

			$aUsers = array( _wpsf__( 'No' ) );
			foreach ( $aEntries as $nKey => $oEntry ) {
				$sIp = $oEntry->ip;

				$aEntry = $oEntry->getRawDataAsArray();

				$aEntry[ 'path' ] = strtoupper( $oEntry->verb ).': <span>'.esc_url( $oEntry->path ).'</span>';
				$aEntry[ 'trans' ] = $oEntry->trans ? _wpsf__( 'Yes' ) : _wpsf__( 'No' );
				$aEntry[ 'ip' ] = $sIp;
				$aEntry[ 'created_at' ] = $this->loadWp()->getTimeStampForDisplay( $aEntry[ 'created_at' ] );
				$aEntry[ 'is_you' ] = $sIp == $sYou;

				if ( $oEntry->uid > 0 ) {
					if ( !isset( $aUsers[ $oEntry->uid ] ) ) {
						$oUser = $oWpUsers->getUserById( $oEntry->uid );
						$aUsers[ $oEntry->uid ] = empty( $oUser ) ? _wpsf__( 'unknown' ) : $oUser->user_login;
					}
				}

				$sCountry = $oGeo->countryName( $sIp );
				if ( empty( $sCountry ) ) {
					$sCountry = _wpsf__( 'Unknown' );
				}
				else {
					$sFlag = $oCon->getPluginUrl_Image( 'flags/'.strtolower( $oGeo->countryIso( $sIp ) ).'.png' );
					$sCountry = sprintf( '<img class="icon-flag" src="%s"/> %s', $sFlag, $sCountry );
				}

				$sIpLink = sprintf( '<a href="%s" target="_blank" title="IP Whois">%s</a>%s',
					$this->getIpWhoisLookup( $sIp ), $sIp,
					$aEntry[ 'is_you' ] ? ' <span style="font-size: smaller;">('._wpsf__( 'You' ).')</span>' : ''
				);

				$aDetails = array(
					sprintf( '%s - %s', _wpsf__( 'IP' ), $sIpLink ),
					sprintf( '%s - %s', _wpsf__( 'Logged-In' ), $aUsers[ $oEntry->uid ] ),
					sprintf( '%s - %s', _wpsf__( 'Location' ), $sCountry ),
					esc_html( esc_js( sprintf( '%s - %s', _wpsf__( 'User Agent' ), $oEntry->ua ) ) )
				);
				$aEntry[ 'visitor' ] = '<div>'.implode( '</div><div>', $aDetails ).'</div>';

				$aEntries[ $nKey ] = $aEntry;
			}
		}
		return $aEntries;
	}

	/**
	 * @param string $sIp
	 * @return string
	 */
	protected function getIpWhoisLookup( $sIp ) {
		return sprintf( 'https://apps.db.ripe.net/db-web-ui/#/query?bflag&searchtext=%s#resultsSection', $sIp );
	}

	/**
	 * @return LiveTrafficTable
	 */
	protected function getTableRenderer() {
		$this->requireCommonLib( 'Components/Tables/LiveTrafficTable.php' );
		/** @var ICWP_WPSF_Processor_Traffic $oTrafficPro */
		$oTrafficPro = $this->loadProcessor();
		$nCount = $oTrafficPro->getProcessorLogger()
							  ->getTrafficEntryCounter()
							  ->all();
		return ( new LiveTrafficTable() )->setTotalRecords( $nCount );
	}

	/**
	 * @return int
	 */
	protected function getDefaultPerPage() {
		return $this->getDef( 'default_per_page' );
	}

	/**
	 * @return array
	 */
	protected function getDisplayStrings() {
		return $this->loadDP()->mergeArraysRecursive(
			parent::getDisplayStrings(),
			array(
				'btn_actions'         => _wpsf__( 'Traffic Watch Log' ),
				'btn_actions_summary' => _wpsf__( 'Review Site Traffic Logs ' ),
			)
		);
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sSectionSlug = $aOptionsParams[ 'slug' ];
		switch ( $sSectionSlug ) {

			case 'section_enable_plugin_feature_traffic' :
				$sTitle = sprintf( _wpsf__( 'Enable Module: %s' ), $this->getMainFeatureName() );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Monitor and review all requests to your site.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), sprintf( _wpsf__( 'Required only if you need to review and investigate and monitor requests to your site' ) ) )
				);
				$sTitleShort = sprintf( _wpsf__( '%s/%s Module' ), _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
				break;

			case 'section_traffic_options' :
				$sTitle = _wpsf__( 'Traffic Watch Options' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Provides finer control over the Traffic Watch system.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), sprintf( _wpsf__( 'These settings are dependent on your requirements.' ), _wpsf__( 'User Management' ) ) )
				);
				$sTitleShort = _wpsf__( 'Traffic Logging Options' );
				break;

			default:
				throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
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

		$sKey = $aOptionsParams[ 'key' ];
		switch ( $sKey ) {

			case 'enable_traffic' :
				$sName = sprintf( _wpsf__( 'Enable %s Module' ), $this->getMainFeatureName() );
				$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Module' ), $this->getMainFeatureName() );
				$sDescription = sprintf( _wpsf__( 'Un-Checking this option will completely disable the %s module.' ), $this->getMainFeatureName() );
				break;

			case 'type_exclusions' :
				$sName = _wpsf__( 'Traffic Log Exclusions' );
				$sSummary = _wpsf__( 'Select Which Types Of Requests To Exclude' );
				$sDescription = _wpsf__( "Select request types that you don't want to appear in the traffic viewer." )
								.'<br/>'._wpsf__( 'If a request matches any exclusion rule, it will not show on the traffic viewer.' );
				break;

			case 'auto_clean' :
				$sName = _wpsf__( 'Auto Expiry Cleaning' );
				$sSummary = _wpsf__( 'Enable Traffic Log Auto Expiry' );
				$sDescription = _wpsf__( 'DB cleanup will delete logs older than this maximum value (in days).' );
				break;

			case 'max_entries' :
				$sName = _wpsf__( 'Max Log Length' );
				$sSummary = _wpsf__( 'Maximum Traffic Log Length To Keep' );
				$sDescription = _wpsf__( 'DB cleanup will delete logs to maintain this maximum number of records.' );
				break;

			case 'auto_disable' :
				$sName = _wpsf__( 'Auto Disable' );
				$sSummary = _wpsf__( 'Auto Disable Traffic Logging After 1 Week' );

				if ( $this->isAutoDisable() ) {
					$sTimestamp = '<br/>'.sprintf( _wpsf__( 'Auto Disable At: %s' ), $this->getAutoDisableTimestamp() );
				}
				else {
					$sTimestamp = '';
				}
				$sDescription = _wpsf__( 'Turn on to prevent unnecessary long-term traffic logging.' )
								.'<br />'._wpsf__( 'Timer resets after options save.' )
								.$sTimestamp;
				break;

			default:
				throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}
}