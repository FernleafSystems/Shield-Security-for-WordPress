<?php
if ( class_exists( 'ICWP_WPSF_FeatureHandler_AuditTrail', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_FeatureHandler_AuditTrail extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return int
	 */
	public function getAutoCleanDays() {
		return (int)$this->getOpt( 'audit_trail_auto_clean' );
	}

	/**
	 * @param array $aAjaxResponse
	 * @return array
	 */
	public function handleAuthAjax( $aAjaxResponse ) {

		if ( empty( $aAjaxResponse ) ) {
			switch ( $this->loadRequest()->request( 'exec' ) ) {

				case 'render_table_audittrail':
					$aAjaxResponse = $this->ajaxExec_BuildTableAuditTrail();
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
	protected function ajaxExec_BuildTableAuditTrail() {
		parse_str( $this->loadRequest()->post( 'filters', '' ), $aFilters );
		$aParams = array_intersect_key(
			array_merge( $_POST, array_map( 'trim', $aFilters ) ),
			array_flip( array(
				'paged',
				'order',
				'orderby',
				'fIp',
				'fContext',
				'fUsername',
				'fLoggedIn',
				'fExludeYou'
			) )
		);
		return array(
			'success' => true,
			'html'    => $this->renderTable( $aParams )
		);
	}

	/**
	 * @param $aParams
	 * @return false|string
	 */
	protected function renderTable( $aParams ) {

		// clean any params of nonsense
		foreach ( $aParams as $sKey => $sValue ) {
			if ( preg_match( '#[^a-z0-9_]#i', $sKey ) || preg_match( '#[^a-z0-9._-]#i', $sValue ) ) {
				unset( $aParams[ $sKey ] );
			}
		}
		$aParams = array_merge(
			array(
				'orderby'    => 'created_at',
				'order'      => 'DESC',
				'paged'      => 1,
				'fIp'        => '',
				'fUsername'  => '',
				'fContext'   => 'all',
				'fLoggedIn'  => -1,
				'fExludeYou' => '',
			),
			$aParams
		);
		$nPage = (int)$aParams[ 'paged' ];

		/** @var ICWP_WPSF_Processor_AuditTrail $oPro */
		$oPro = $this->loadProcessor();
		$oSelector = $oPro->getQuerySelector()
						  ->setPage( $nPage )
						  ->setOrderBy( $aParams[ 'orderby' ], $aParams[ 'order' ] )
						  ->setLimit( $this->getDefaultPerPage() )
						  ->setResultsAsVo( true );
		// Filters
		{
			$oIp = $this->loadIpService();
			// If an IP is specified, it takes priority
			if ( $oIp->isValidIp( $aParams[ 'fIp' ] ) ) {
				$oSelector->filterByIp( $aParams[ 'fIp' ] );
			}
			else if ( $aParams[ 'fExludeYou' ] == 'Y' ) {
				$oSelector->filterByNotIp( $oIp->getRequestIp() );
			}

			// if username is provided, this takes priority over "logged-in" (even if it's invalid)
			if ( !empty( $aParams[ 'fUsername' ] ) ) {
				$oUser = $this->loadWpUsers()->getUserByUsername( $aParams[ 'fUsername' ] );
				if ( !empty( $oUser ) ) {
					$oSelector->filterByUsername( $oUser->user_login );
				}
			}
			else if ( $aParams[ 'fLoggedIn' ] >= 0 ) {
				$oSelector->filterByIsLoggedIn( $aParams[ 'fLoggedIn' ] );
			}
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
	 * @return AuditTrailTable
	 */
	protected function getTableRenderer() {
		$this->requireCommonLib( 'Components/Tables/AuditTrailTable.php' );
		/** @var ICWP_WPSF_Processor_AuditTrail $oPro */
		$oPro = $this->loadProcessor();
		$nCount = $oPro->countAuditEntriesForContext();

		$oTable = new AuditTrailTable();
		return $oTable->setTotalRecords( $nCount );
	}

	/**
	 * @return int
	 */
	protected function getDefaultPerPage() {
		return $this->getDef( 'audit_trail_default_per_page' );
	}

	/**
	 * @return int
	 */
	protected function getDefaultMaxEntries() {
		return $this->getDef( 'audit_trail_default_max_entries' );
	}

	/**
	 * @return int
	 */
	public function getMaxEntries() {
		return $this->isPremium() ? (int)$this->getOpt( 'audit_trail_max_entries' ) : $this->getDefaultMaxEntries();
	}

	/**
	 * @return bool
	 */
	public function isAuditEmails() {
		return $this->isOpt( 'enable_audit_context_emails', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isAuditPlugins() {
		return $this->isOpt( 'enable_audit_context_plugins', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isAuditPosts() {
		return $this->isOpt( 'enable_audit_context_posts', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isAuditShield() {
		return $this->isOpt( 'enable_audit_context_wpsf', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isAuditThemes() {
		return $this->isOpt( 'enable_audit_context_themes', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isAuditUsers() {
		return $this->isOpt( 'enable_audit_context_users', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isAuditWp() {
		return $this->isOpt( 'enable_audit_context_wordpress', 'Y' );
	}

	/**
	 * Move to table
	 * @param ICWP_WPSF_AuditTrailEntryVO[] $aEntries
	 * @return array
	 */
	public function formatEntriesForDisplay( $aEntries ) {
		if ( is_array( $aEntries ) ) {
			$oDp = $this->loadDP();
			$oWp = $this->loadWp();
			$sYou = $this->loadIpService()->getRequestIp();
			$oCarbon = new \Carbon\Carbon();
			foreach ( $aEntries as $nKey => $oEntry ) {
				$aE = $oDp->convertStdClassToArray( $oEntry->getRawData() );
				$aE[ 'event' ] = str_replace( '_', ' ', sanitize_text_field( $oEntry->getEvent() ) );
				$aE[ 'message' ] = stripslashes( sanitize_text_field( $oEntry->getMessage() ) );
				$aE[ 'created_at' ] = $oCarbon->setTimestamp( $oEntry->getCreatedAt() )->diffForHumans()
									  .'<br/><small>'.$oWp->getTimeStringForDisplay( $oEntry->getCreatedAt() ).'</small>';
				if ( $oEntry->getIp() == $sYou ) {
					$aE[ 'ip' ] .= '<br /><div style="font-size: smaller;">('._wpsf__( 'Your IP' ).')</div>';
				}
				$aEntries[ $nKey ] = $aE;
			}
		}
		return $aEntries;
	}

	/**
	 * @return array
	 */
	public function getAllContexts() {
		return array(
			'all'       => 'All', //special
			'wpsf'      => $this->getConn()->getHumanName(),
			'wordpress' => 'WordPress',
			'users'     => 'Users',
			'posts'     => 'Posts',
			'plugins'   => 'Plugins',
			'themes'    => 'Themes',
			'emails'    => 'Emails',
		);
	}

	/**
	 * @return array
	 */
	protected function getDisplayStrings() {
		return $this->loadDP()->mergeArraysRecursive(
			parent::getDisplayStrings(),
			array(
				'btn_actions'         => _wpsf__( 'Audit Trail Viewer' ),
				'btn_actions_summary' => _wpsf__( 'Review audit trail logs ' ),

				'at_users'            => _wpsf__( 'Users' ),
				'at_plugins'          => _wpsf__( 'Plugins' ),
				'at_themes'           => _wpsf__( 'Themes' ),
				'at_wordpress'        => _wpsf__( 'WordPress' ),
				'at_posts'            => _wpsf__( 'Posts' ),
				'at_emails'           => _wpsf__( 'Emails' ),
				'at_time'             => _wpsf__( 'Time' ),
				'at_event'            => _wpsf__( 'Event' ),
				'at_message'          => _wpsf__( 'Message' ),
				'at_username'         => _wpsf__( 'Username' ),
				'at_category'         => _wpsf__( 'Category' ),
				'at_ipaddress'        => _wpsf__( 'IP Address' ),
				'at_you'              => _wpsf__( 'You' ),
				'at_no_audit_entries' => _wpsf__( 'There are currently no audit entries this is section.' ),
			)
		);
	}

	/**
	 * See plugin controller for the nature of $aData wpPrivacyExport()
	 * @param array  $aExportItems
	 * @param string $sEmail
	 * @param int    $nPage
	 * @return array
	 */
	public function onWpPrivacyExport( $aExportItems, $sEmail, $nPage = 1 ) {
		/** @var ICWP_WPSF_Processor_AuditTrail $oProc */
		$oProc = $this->getProcessor();

		$oUser = $this->loadWpUsers()->getUserByEmail( $sEmail );

		$aExportItem = array(
			'group_id'    => $this->prefix(),
			'group_label' => sprintf( _wpsf__( '[%s] Audit Trail Entries' ), $this->getConn()->getHumanName() ),
			'item_id'     => $this->prefix( 'audit-trail' ),
			'data'        => array(),
		);

		try {
			$oFinder = $oProc->getQuerySelector()
							 ->addWhereSearch( 'wp_username', $oUser->user_login )
							 ->setResultsAsVo( true );

			$oWp = $this->loadWp();
			foreach ( $oFinder->query() as $oEntry ) {
				$aExportItem[ 'data' ][] = array(
					$sTimeStamp = $oWp->getTimeStringForDisplay( $oEntry->getCreatedAt() ),
					'name'  => sprintf( '[%s] Audit Trail Entry', $sTimeStamp ),
					'value' => sprintf( '[IP:%s] %s', $oEntry->getIp(), $oEntry->getMessage() )
				);
			}

			if ( !empty( $aExportItem[ 'data' ] ) ) {
				$aExportItems[] = $aExportItem;
			}
		}
		catch ( Exception $oE ) {
		}

		return $aExportItems;
	}

	/**
	 * See plugin controller for the nature of $aData wpPrivacyErase()
	 * @param array  $aData
	 * @param string $sEmail
	 * @param int    $nPage
	 * @return array
	 */
	public function onWpPrivacyErase( $aData, $sEmail, $nPage = 1 ) {

		/** @var ICWP_WPSF_Processor_AuditTrail $oProc */
		$oProc = $this->getProcessor();

		try {
			$oThisUsername = $this->loadWpUsers()->getUserByEmail( $sEmail )->user_login;
			$oProc->getQueryDeleter()
				  ->addWhereSearch( 'wp_username', $oThisUsername )
				  ->all();
			$aData[ 'messages' ][] = sprintf( '%s Audit Entries deleted', $this->getConn()->getHumanName() );
		}
		catch ( Exception $oE ) {
		}
		return $aData;
	}

	/**
	 * @return string
	 */
	public function getAuditTrailTableName() {
		return $this->prefix( $this->getDef( 'audit_trail_table_name' ), '_' );
	}

	/**
	 * @param array $aAllData
	 * @return array
	 */
	public function addInsightsConfigData( $aAllData ) {
		$aThis = array(
			'strings'  => array(
				'title' => _wpsf__( 'Activity Audit Log' ),
				'sub'   => _wpsf__( 'Track Activity: What, Who, When, Where' ),
			),
			'key_opts' => array()
		);

		if ( !$this->isModOptEnabled() ) {
			$aThis[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {
			$aAudit = array();
			$aNonAudit = array();
			$this->isAuditShield() ? $aAudit[] = 'Shield' : $aNonAudit[] = 'Shield';
			$this->isAuditUsers() ? $aAudit[] = _wpsf__( 'users' ) : $aNonAudit[] = _wpsf__( 'users' );
			$this->isAuditPlugins() ? $aAudit[] = _wpsf__( 'plugins' ) : $aNonAudit[] = _wpsf__( 'plugins' );
			$this->isAuditThemes() ? $aAudit[] = _wpsf__( 'themes' ) : $aNonAudit[] = _wpsf__( 'themes' );
			$this->isAuditPosts() ? $aAudit[] = _wpsf__( 'posts' ) : $aNonAudit[] = _wpsf__( 'posts' );
			$this->isAuditEmails() ? $aAudit[] = _wpsf__( 'emails' ) : $aNonAudit[] = _wpsf__( 'emails' );
			$this->isAuditWp() ? $aAudit[] = 'WP' : $aNonAudit[] = 'WP';

			if ( empty( $aNonAudit ) ) {
				$aThis[ 'key_opts' ][ 'audit' ] = array(
					'name'    => _wpsf__( 'Audit Areas' ),
					'enabled' => true,
					'summary' => _wpsf__( 'All important events on your site are being logged' ),
					'weight'  => 2,
					'href'    => $this->getUrl_DirectLinkToSection( 'section_enable_audit_contexts' ),
				);
			}
			else if ( empty( $aAudit ) ) {
				$aThis[ 'key_opts' ][ 'audit' ] = array(
					'name'    => _wpsf__( 'Audit Areas' ),
					'enabled' => false,
					'summary' => sprintf( _wpsf__( 'No areas are set to be audited: %s' ), implode( ', ', $aAudit ) ),
					'weight'  => 2,
					'href'    => $this->getUrl_DirectLinkToSection( 'section_enable_audit_contexts' ),
				);
			}
			else {
//				$aThis[ 'key_opts' ][ 'audit' ] = array(
//					'name'    => _wpsf__( 'Audit Areas' ),
//					'enabled' => true,
//					'summary' => sprintf( _wpsf__( 'Important areas are being audited: %s' ), implode( ', ', $aAudit ) ),
//					'weight'  => 2,
//					'href'    => $this->getUrl_DirectLinkToSection( 'section_enable_audit_contexts' ),
//				);
				$aThis[ 'key_opts' ][ 'nonaudit' ] = array(
					'name'    => _wpsf__( 'Audit Events' ),
					'enabled' => false,
					'summary' => sprintf( _wpsf__( "Important events aren't being audited: %s" ), implode( ', ', $aNonAudit ) ),
					'weight'  => 2,
					'href'    => $this->getUrl_DirectLinkToSection( 'section_enable_audit_contexts' ),
				);
			}

			$aThis[ 'key_opts' ][ 'length' ] = array(
				'name'    => _wpsf__( 'Audit Trail' ),
				'enabled' => true,
				'summary' => sprintf( _wpsf__( 'Maximum Audit Trail entries limited to %s' ), $this->getMaxEntries() ),
				'weight'  => 0,
				'href'    => $this->getUrl_DirectLinkToOption( 'audit_trail_max_entries' ),
			);
		}

		$aAllData[ $this->getSlug() ] = $aThis;
		return $aAllData;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sSectionSlug = $aOptionsParams[ 'slug' ];
		switch ( $sSectionSlug ) {

			case 'section_enable_plugin_feature_audit_trail' :
				$sTitle = sprintf( _wpsf__( 'Enable Module: %s' ), $this->getMainFeatureName() );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'The Audit Trail is designed so you can look back on events and analyse what happened and what may have gone wrong.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'Audit Trail' ) ) )
				);
				$sTitleShort = sprintf( _wpsf__( '%s/%s Module' ), _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
				break;

			case 'section_audit_trail_options' :
				$sTitle = _wpsf__( 'Audit Trail Options' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Provides finer control over the audit trail itself.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( 'These settings are dependent on your requirements.' ) )
				);
				$sTitleShort = _wpsf__( 'Options' );
				break;

			case 'section_enable_audit_contexts' :
				$sTitle = _wpsf__( 'Enable Audit Contexts' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Specify which types of actions on your site are logged.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( 'These settings are dependent on your requirements.' ) )
				);
				$sTitleShort = _wpsf__( 'Audit Contexts' );
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

		$oCon = self::getConn();

		$sKey = $aOptionsParams[ 'key' ];
		switch ( $sKey ) {

			case 'enable_audit_trail' :
				$sName = sprintf( _wpsf__( 'Enable %s Module' ), $this->getMainFeatureName() );
				$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Module' ), $this->getMainFeatureName() );
				$sDescription = sprintf( _wpsf__( 'Un-Checking this option will completely disable the %s module.' ), $this->getMainFeatureName() );
				break;

			case 'audit_trail_max_entries' :
				$sName = _wpsf__( 'Max Trail Length' );
				$sSummary = _wpsf__( 'Maximum Audit Trail Length To Keep' );
				$sDescription = _wpsf__( 'Automatically remove any audit trail entries when this limit is exceeded.' )
								.'<br/>'.sprintf( '%s: %s', _wpsf__( 'Default' ), $this->getDefaultMaxEntries() );
				break;

			case 'audit_trail_auto_clean' :
				$sName = _wpsf__( 'Auto Clean' );
				$sSummary = _wpsf__( 'Enable Audit Auto Cleaning' );
				$sDescription = _wpsf__( 'Events older than the number of days specified will be automatically cleaned from the database.' );
				break;

			case 'enable_audit_context_users' :
				$sName = _wpsf__( 'Users And Logins' );
				$sSummary = sprintf( _wpsf__( 'Enable Audit Context - %s' ), _wpsf__( 'Users And Logins' ) );
				$sDescription = sprintf( _wpsf__( 'When this context is enabled, the audit trail will track activity relating to: %s' ), _wpsf__( 'Users And Logins' ) );
				break;

			case 'enable_audit_context_plugins' :
				$sName = _wpsf__( 'Plugins' );
				$sSummary = sprintf( _wpsf__( 'Enable Audit Context - %s' ), _wpsf__( 'Plugins' ) );
				$sDescription = sprintf( _wpsf__( 'When this context is enabled, the audit trail will track activity relating to: %s' ), _wpsf__( 'WordPress Plugins' ) );
				break;

			case 'enable_audit_context_themes' :
				$sName = _wpsf__( 'Themes' );
				$sSummary = sprintf( _wpsf__( 'Enable Audit Context - %s' ), _wpsf__( 'Themes' ) );
				$sDescription = sprintf( _wpsf__( 'When this context is enabled, the audit trail will track activity relating to: %s' ), _wpsf__( 'WordPress Themes' ) );
				break;

			case 'enable_audit_context_posts' :
				$sName = _wpsf__( 'Posts And Pages' );
				$sSummary = sprintf( _wpsf__( 'Enable Audit Context - %s' ), _wpsf__( 'Posts And Pages' ) );
				$sDescription = sprintf( _wpsf__( 'When this context is enabled, the audit trail will track activity relating to: %s' ), _wpsf__( 'Editing and publishing of posts and pages' ) );
				break;

			case 'enable_audit_context_wordpress' :
				$sName = _wpsf__( 'WordPress And Settings' );
				$sSummary = sprintf( _wpsf__( 'Enable Audit Context - %s' ), _wpsf__( 'WordPress And Settings' ) );
				$sDescription = sprintf( _wpsf__( 'When this context is enabled, the audit trail will track activity relating to: %s' ), _wpsf__( 'WordPress upgrades and changes to particular WordPress settings' ) );
				break;

			case 'enable_audit_context_emails' :
				$sName = _wpsf__( 'Emails' );
				$sSummary = sprintf( _wpsf__( 'Enable Audit Context - %s' ), _wpsf__( 'Emails' ) );
				$sDescription = sprintf( _wpsf__( 'When this context is enabled, the audit trail will track activity relating to: %s' ), _wpsf__( 'Email Sending' ) );
				break;

			case 'enable_audit_context_wpsf' :
				$sName = $oCon->getHumanName();
				$sSummary = sprintf( _wpsf__( 'Enable Audit Context - %s' ), $oCon->getHumanName() );
				$sDescription = sprintf( _wpsf__( 'When this context is enabled, the audit trail will track activity relating to: %s' ), $oCon->getHumanName() );
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