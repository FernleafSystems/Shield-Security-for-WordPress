<?php

if ( class_exists( 'ICWP_WPSF_FeatureHandler_Insights', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_FeatureHandler_Insights extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @param array $aData
	 */
	protected function displayModulePage( $aData = array() ) {
		$oCon = $this->getConn();
		$aSecNotices = $this->getNotices();

		$nNoticesCount = 0;
		foreach ( $aSecNotices as $aNoticeSection ) {
			$nNoticesCount += isset( $aNoticeSection[ 'count' ] ) ? $aNoticeSection[ 'count' ] : 0;
		}

		$sSubNavSection = $this->loadRequest()->query( 'subnav' );

		/** @var ICWP_WPSF_FeatureHandler_Traffic $oTrafficMod */
		$oTrafficMod = $oCon->getModule( 'traffic' );
		/** @var ICWP_WPSF_FeatureHandler_AuditTrail $oAuditMod */
		$oAuditMod = $oCon->getModule( 'audit_trail' );
		/** @var ICWP_WPSF_FeatureHandler_Ips $oIpMod */
		$oIpMod = $oCon->getModule( 'ips' );

		switch ( $sSubNavSection ) {

			case 'notes':
				$aData = array(
					'vars'  => array(),
					'ajax'  => array(
						'admin_note_new'     => $this->getAjaxActionData( 'admin_note_new' ),
						'admin_notes_render' => $this->getAjaxActionData( 'admin_notes_render' ),
						'admin_notes_delete' => $this->getAjaxActionData( 'admin_notes_delete' ),
					),
					'flags' => array(
						'can_notes' => $this->isPremium() //not the way to determine
					)
				);
				break;

			case 'config':
				$aData = array(
					'vars' => array(
						'config_cards' => $this->getConfigCardsData()
					)
				);
				break;

			case 'ips':
				$aData = array(
					'ajax'    => array(
						'render_table_ip' => $oIpMod->getAjaxActionData( 'render_table_ip', true ),
						'add_ip_white'    => $oIpMod->getAjaxActionData( 'add_ip_white' ),
						'ip_delete'       => $oIpMod->getAjaxActionData( 'ip_delete', true ),
					),
					'flags'   => array(),
					'strings' => array(),
					'vars'    => array(),
				);
				break;

			case 'audit':
				$aData = array(
					'ajax'    => array(
						'render_table_audittrail' => $oAuditMod->getAjaxActionData( 'render_table_audittrail', true )
					),
					'flags'   => array(),
					'strings' => array(
						'title_filter_form' => _wpsf__( 'Audit Trail Filters' ),
					),
					'vars'    => array(
						'contexts_for_select' => $oAuditMod->getAllContexts(),
					),
				);
				break;

			case 'traffic':
				$aData = array(
					'ajax'    => array(
						'render_table_traffic' => $oTrafficMod->getAjaxActionData( 'render_table_traffic', true )
					),
					'flags'   => array(),
					'strings' => array(
						'title_filter_form' => _wpsf__( 'Traffic Table Filters' ),
					),
					'vars'    => array(),
				);
				break;

			case 'scan':
				$aData = array();
				break;

			case 'original':
				$aData = array(
					'vars'   => array(
						'summary'               => $this->getInsightsModsSummary(),
						'insight_events'        => $this->getRecentEvents(),
						'insight_notices'       => $aSecNotices,
						'insight_notices_count' => $nNoticesCount,
						'insight_stats'         => $this->getStats(),
					),
					'inputs' => array(
						'license_key' => array(
							'name'      => $this->prefixOptionKey( 'license_key' ),
							'maxlength' => $this->getDef( 'license_key_length' ),
						)
					),
					'ajax'   => array(
						'admin_note_new'     => $this->getAjaxActionData( 'admin_note_new' ),
						'admin_notes_render' => $this->getAjaxActionData( 'admin_notes_render' ),
						'admin_notes_delete' => $this->getAjaxActionData( 'admin_notes_delete' ),
					),
					'hrefs'  => array(
						'shield_pro_url'           => 'https://icwp.io/shieldpro',
						'shield_pro_more_info_url' => 'https://icwp.io/shld1',
					),
					'flags'  => array(
						'show_ads'              => false,
						'show_standard_options' => false,
						'show_alt_content'      => true,
						'is_pro'                => $this->isPremium(),
						'has_notices'           => count( $aSecNotices ) > 0,
					),
				);
				break;
			case 'insights':
			case 'index':
			default:
				$sSubNavSection = 'insights';
				$aData = array(
					'vars'   => array(
						'summary'               => $this->getInsightsModsSummary(),
						'insight_events'        => $this->getRecentEvents(),
						'insight_notices'       => $aSecNotices,
						'insight_notices_count' => $nNoticesCount,
						'insight_stats'         => $this->getStats(),
					),
					'inputs' => array(
						'license_key' => array(
							'name'      => $this->prefixOptionKey( 'license_key' ),
							'maxlength' => $this->getDef( 'license_key_length' ),
						)
					),
					'ajax'   => array(),
					'hrefs'  => array(
						'shield_pro_url'           => 'https://icwp.io/shieldpro',
						'shield_pro_more_info_url' => 'https://icwp.io/shld1',
					),
					'flags'  => array(
						'show_ads'              => false,
						'show_standard_options' => false,
						'show_alt_content'      => true,
						'is_pro'                => $this->isPremium(),
						'has_notices'           => count( $aSecNotices ) > 0,
					),
				);
				break;
		}

		$aTopNav = array(
			'insights' => _wpsf__( 'Overview' ),
			'config'   => _wpsf__( 'Configuration' ),
			'scan'     => _wpsf__( 'Scan' ),
			'ips'      => _wpsf__( 'IP Lists' ),
			'audit'    => _wpsf__( 'Audit Trail' ),
			'traffic'  => _wpsf__( 'Traffic' ),
			'notes'    => _wpsf__( 'Notes' ),
			'original' => _wpsf__( 'ORIGINAL' ),
		);
		array_walk( $aTopNav, function ( &$sName, $sKey ) use ( $sSubNavSection ) {
			$sName = array(
				'href'   => add_query_arg( [ 'subnav' => $sKey ], $this->getUrl_AdminPage() ),
				'name'   => $sName,
				'active' => $sKey === $sSubNavSection
			);
		} );

		$aData = $this->loadDP()
					  ->mergeArraysRecursive(
						  array(
							  'hrefs'   => array(
								  'nav_home' => $this->getUrl_AdminPage(),
								  'top_nav'  => $aTopNav,
							  ),
							  'strings' => $this->getDisplayStrings(),
						  ),
						  $aData
					  );

		echo $this->renderTemplate( sprintf( '/wpadmin_pages/insights_new/%s/index.twig', $sSubNavSection ), $aData, true );
	}

	public function insertCustomJsVars_Admin() {
		parent::insertCustomJsVars_Admin();

		if ( $this->isThisModulePage() ) {

			if ( $this->isThisModulePage() ) {
				$oConn = $this->getConn();

				switch ( $this->loadRequest()->query( 'subnav' ) ) {

					case 'notes':
						$sAsset = 'shield-notes';
						$sUnique = $this->prefix( $sAsset );
						wp_register_script(
							$sUnique,
							$oConn->getPluginUrl_Js( $sAsset.'.js' ),
							array( $this->prefix( 'plugin' ) ),
							$oConn->getVersion(),
							true
						);
						wp_enqueue_script( $sUnique );

						wp_localize_script(
							$sUnique,
							'icwp_wpsf_vars_insights',
							array(
								'ajax_admin_notes_render' => $this->getAjaxActionData( 'admin_notes_render' ),
								'ajax_admin_notes_delete' => $this->getAjaxActionData( 'admin_notes_delete' ),
							)
						);
						break;

					case 'audit':
					case 'traffic':
						$sAsset = 'shield-tables';
						$sUnique = $this->prefix( $sAsset );
						wp_register_script(
							$sUnique,
							$oConn->getPluginUrl_Js( $sAsset.'.js' ),
							array( $this->prefix( 'plugin' ) ),
							$oConn->getVersion(),
							false
						);
						wp_enqueue_script( $sUnique );
						break;

					case 'ips':
						$sAsset = 'shield-ips';
						$sUnique = $this->prefix( $sAsset );
						wp_register_script(
							$sUnique,
							$oConn->getPluginUrl_Js( $sAsset.'.js' ),
							array( $this->prefix( 'plugin' ) ),
							$oConn->getVersion(),
							false
						);
						wp_enqueue_script( $sUnique );
						break;
				}
			}
		}
	}

	/**
	 * @param array $aAjaxResponse
	 * @return array
	 */
	public function handleAuthAjax( $aAjaxResponse ) {

		if ( empty( $aAjaxResponse ) ) {
			switch ( $this->loadRequest()->request( 'exec' ) ) {

				case 'admin_note_new':
					$aAjaxResponse = $this->ajaxExec_AdminNoteNew();
					break;

				case 'admin_notes_delete':
					$aAjaxResponse = $this->ajaxExec_AdminNotesDelete();
					break;

				case 'admin_notes_render':
					$aAjaxResponse = $this->ajaxExec_AdminNotesRender();
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
	protected function ajaxExec_AdminNoteNew() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getConn()->getModule( 'plugin' );
		$sNote = $this->loadRequest()->post( 'admin_note', '' );
		$bSuccess = false;
		if ( !$oMod->getCanAdminNotes() ) {
			$sMessage = _wpsf__( 'Sorry, Admin Notes is only available for Pro subscriptions.' );
		}
		else if ( empty( $sNote ) ) {
			$sMessage = _wpsf__( 'Sorry, but it appears your note was empty.' );
		}
		else {
			/** @var ICWP_WPSF_Processor_Plugin $oP */
			$oP = $oMod->getProcessor();
			$bSuccess = $oP->getSubProcessorNotes()
						   ->getQueryInserter()
						   ->create( $sNote ) !== false;

			$sMessage = $bSuccess ? _wpsf__( 'Note created successfully.' ) : _wpsf__( 'Note could not be created.' );
		}
		return array(
			'success' => $bSuccess,
			'message' => $sMessage
		);
	}

	/**
	 * @return array
	 */
	protected function ajaxExec_AdminNotesDelete() {

		$nNoteId = (int)$this->loadRequest()->post( 'note_id', 0 );
		if ( $nNoteId >= 0 ) {
			/** @var ICWP_WPSF_FeatureHandler_Plugin $oMod */
			$oMod = $this->getConn()->getModule( 'plugin' );
			/** @var ICWP_WPSF_Processor_Plugin $oP */
			$oP = $oMod->getProcessor();
			$oP->getSubProcessorNotes()
			   ->getQueryDeleter()
			   ->deleteById( $nNoteId );
		}

		return array(
			'success' => true
		);
	}

	/**
	 * @return array
	 */
	protected function ajaxExec_AdminNotesRender() {
		$aNotes = $this->getNotes();
		$sHtml = $this->renderTemplate(
			'/wpadmin_pages/insights_new/notes/admin_notes_table.twig',
			array(
				'vars'  => array(
					'insight_notes' => $aNotes,
				),
				'flags' => array(
					'has_notes' => count( $aNotes ) > 0,
					'can_notes' => $this->isPremium() //not the way to determine
				),
			),
			true
		);

		$bSuccess = true;
		return array(
			'success' => $bSuccess,
			'html'    => $sHtml
		);
	}

	/**
	 * @return array
	 */
	protected function getDisplayStrings() {
		$sName = $this->getConn()->getHumanName();
		return $this->loadDP()->mergeArraysRecursive(
			parent::getDisplayStrings(),
			array(
				'page_title'          => sprintf( _wpsf__( '%s Security Insights' ), $sName ),
				'recommendation'      => ucfirst( _wpsf__( 'recommendation' ) ),
				'suggestion'          => ucfirst( _wpsf__( 'suggestion' ) ),
				'box_welcome_title'   => sprintf( _wpsf__( 'Welcome To %s Security Insights Dashboard' ), $sName ),
				'box_receve_subtitle' => sprintf( _wpsf__( 'Some of the most recent %s events' ), $sName )
			)
		);
	}

	/**
	 * @return array[]
	 */
	protected function getInsightsModsSummary() {
		$aMods = array();
		foreach ( $this->getModulesSummaryData() as $aMod ) {
			if ( !in_array( $aMod[ 'slug' ], array( 'insights' ) ) ) {
				$aMods[] = $aMod;
			}
		}
		return $aMods;
	}

	/**
	 * @return array[]
	 */
	protected function getIps() {
		/** @var ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getConn()
					 ->getModule( 'ips' );
		/** @var ICWP_WPSF_Processor_Ips $oPro */
		$oPro = $oMod->getProcessor();

		$aData = array(
			'white' => $this->parseIpList( $oPro->getWhitelistIpsData() ),
			'black' => $this->parseIpList( $oPro->getAutoBlacklistIpsData() ),
		);
		$aData[ 'has_white' ] = !empty( $aData[ 'white' ] );
		$aData[ 'has_black' ] = !empty( $aData[ 'black' ] );
		return $aData;
	}

	/**
	 * @param ICWP_WPSF_IpsEntryVO[] $aList
	 * @return array[]
	 */
	private function parseIpList( $aList ) {
		/** @var ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getConn()
					 ->getModule( 'ips' );
		$aParsed = array();

		$oIpService = $this->loadIpService();
		$oDp = $this->loadDP();
		$oCarbon = new \Carbon\Carbon();
		foreach ( $aList as $oIp ) {

			$nTrans = $oIp->getTransgressions();
			$aIp = $oDp->convertStdClassToArray( $oIp->getRawData() );
			$aIp[ 'trans' ] = sprintf( _n( '%s offence', '%s offences', $nTrans, 'wp-simple-firewall' ), $nTrans );
			$aIp[ 'last_access_at' ] = $oCarbon->setTimestamp( $oIp->getLastAccessAt() )->diffForHumans();
			$aIp[ 'created_at' ] = $oCarbon->setTimestamp( $oIp->getCreatedAt() )->diffForHumans();
			$aIp[ 'blocked' ] = $nTrans >= $oMod->getOptTransgressionLimit();
			try {
				$aIp[ 'is_you' ] = $oIpService->checkIp( $oIpService->getRequestIp(), $oIp->getIp() );
			}
			catch ( Exception $oE ) {
				$aIp[ 'is_you' ] = false;
			}

			$aIp[ 'is_you' ] ? array_unshift( $aParsed, $aIp ) : array_push( $aParsed, $aIp );
		}
		return $aParsed;
	}

	/**
	 * @return array[]
	 */
	protected function getConfigCardsData() {
		$aAll = apply_filters( $this->prefix( 'collect_summary' ), array() );

		return $aAll;
	}

	/**
	 * @return array[]
	 */
	protected function getNotices() {
		$aAll = apply_filters(
			$this->prefix( 'collect_notices' ),
			array(
				'plugins' => $this->getNoticesPlugins(),
				'themes'  => $this->getNoticesThemes(),
				'core'    => $this->getNoticesCore(),
			)
		);

		// order and then remove empties
		return array_filter(
			array_merge(
				array(
					'site'      => array(),
					'sec_admin' => array(),
					'scans'     => array(),
					'core'      => array(),
					'plugins'   => array(),
					'themes'    => array(),
					'users'     => array(),
					'lockdown'  => array(),
				),
				$aAll
			),
			function ( $aSection ) {
				return !empty( $aSection[ 'count' ] );
			}
		);
	}

	protected function getNoticesSite() {
		$oSslService = $this->loadSslService();

		$aNotices = array(
			'title'    => _wpsf__( 'Site' ),
			'messages' => array()
		);

		// SSL Expires
		$sHomeUrl = $this->loadWp()->getHomeUrl();
		$bHomeSsl = strpos( $sHomeUrl, 'https://' ) === 0;

		if ( $bHomeSsl && $oSslService->isEnvSupported() ) {

			try {
				// first verify SSL cert:
				$oSslService->getCertDetailsForDomain( $sHomeUrl );

				// If we didn't throw and exception, we got it.
				$nExpiresAt = $oSslService->getExpiresAt( $sHomeUrl );
				if ( $nExpiresAt > 0 ) {
					$nTimeLeft = ( $nExpiresAt - $this->loadRequest()->ts() );
					$bExpired = $nTimeLeft < 0;
					$nDaysLeft = $bExpired ? 0 : (int)round( $nTimeLeft/DAY_IN_SECONDS, 0, PHP_ROUND_HALF_DOWN );

					if ( $nDaysLeft < 15 ) {

						if ( $bExpired ) {
							$sMess = _wpsf__( 'SSL certificate for this site has expired.' );
						}
						else {
							$sMess = sprintf( _wpsf__( 'SSL certificate will expire soon (in %s days)' ), $nDaysLeft );
						}

						$aMessage = array(
							'title'   => 'SSL Cert Expiration',
							'message' => $sMess,
							'href'    => '',
							'rec'     => _wpsf__( 'Check or renew your SSL certificate.' )
						);
					}
				}
			}
			catch ( Exception $oE ) {
				$aMessage = array(
					'title'   => 'SSL Cert Expiration',
					'message' => 'Failed to retrieve a valid SSL certificate.',
					'href'    => ''
				);
			}

			if ( !empty( $aMessage ) ) {
				$aNotices[ 'messages' ][ 'ssl_cert' ] = $aMessage;
			}
		}

		{ // db password strength
			$nStrength = ( new \ZxcvbnPhp\Zxcvbn() )->passwordStrength( DB_PASSWORD )[ 'score' ];
			if ( $nStrength < 4 ) {
				$aNotices[ 'messages' ][ 'db_strength' ] = array(
					'title'   => 'DB Password',
					'message' => _wpsf__( 'DB Password appears to be weak.' ),
					'href'    => '',
					'rec'     => _wpsf__( 'The database password should be strong.' )
				);
			}
		}

		$aNotices[ 'count' ] = count( $aNotices[ 'messages' ] );
		return $aNotices;
	}

	/**
	 * @return array
	 */
	protected function getNoticesPlugins() {
		$oWpPlugins = $this->loadWpPlugins();
		$aNotices = array(
			'title'    => _wpsf__( 'Plugins' ),
			'messages' => array()
		);

		{// Inactive
			$nCount = count( $oWpPlugins->getPlugins() ) - count( $oWpPlugins->getActivePlugins() );
			if ( $nCount > 0 ) {
				$aNotices[ 'messages' ][ 'inactive' ] = array(
					'title'   => 'Inactive',
					'message' => sprintf( _wpsf__( '%s inactive plugin(s)' ), $nCount ),
					'href'    => $this->loadWp()->getAdminUrl_Plugins( true ),
					'action'  => sprintf( 'Go To %s', _wpsf__( 'Plugins' ) ),
					'rec'     => _wpsf__( 'Unused plugins should be removed.' )
				);
			}
		}

		{// updates
			$nCount = count( $oWpPlugins->getUpdates() );
			if ( $nCount > 0 ) {
				$aNotices[ 'messages' ][ 'updates' ] = array(
					'title'   => 'Updates',
					'message' => sprintf( _wpsf__( '%s plugin update(s)' ), $nCount ),
					'href'    => $this->loadWp()->getAdminUrl_Updates( true ),
					'action'  => sprintf( 'Go To %s', _wpsf__( 'Updates' ) ),
					'rec'     => _wpsf__( 'Updates should be applied as early as possible.' )
				);
			}
		}

		$aNotices[ 'count' ] = count( $aNotices[ 'messages' ] );
		return $aNotices;
	}

	/**
	 * @return array
	 */
	protected function getNoticesThemes() {
		$oWpT = $this->loadWpThemes();
		$aNotices = array(
			'title'    => _wpsf__( 'Themes' ),
			'messages' => array()
		);

		{// Inactive
			$nInactive = count( $oWpT->getThemes() ) - ( $oWpT->isActiveThemeAChild() ? 2 : 1 );
			if ( $nInactive > 0 ) {
				$aNotices[ 'messages' ][ 'inactive' ] = array(
					'title'   => 'Inactive',
					'message' => sprintf( _wpsf__( '%s inactive themes(s)' ), $nInactive ),
					'href'    => $this->loadWp()->getAdminUrl_Themes( true ),
					'action'  => sprintf( 'Go To %s', _wpsf__( 'Themes' ) ),
					'rec'     => _wpsf__( 'Unused themes should be removed.' )
				);
			}
		}

		{// updates
			$nCount = count( $oWpT->getUpdates() );
			if ( $nCount > 0 ) {
				$aNotices[ 'messages' ][ 'updates' ] = array(
					'title'   => 'Updates',
					'message' => sprintf( _wpsf__( '%s theme update(s)' ), $nCount ),
					'href'    => $this->loadWp()->getAdminUrl_Updates( true ),
					'action'  => sprintf( 'Go To %s', _wpsf__( 'Updates' ) ),
					'rec'     => _wpsf__( 'Updates should be applied as early as possible.' )
				);
			}
		}

		$aNotices[ 'count' ] = count( $aNotices[ 'messages' ] );
		return $aNotices;
	}

	/**
	 * @return array
	 */
	protected function getNoticesCore() {
		$oWp = $this->loadWp();
		$aNotices = array(
			'title'    => _wpsf__( 'WordPress Core' ),
			'messages' => array()
		);

		{// updates
			if ( $oWp->hasCoreUpdate() ) {
				$aNotices[ 'messages' ][ 'updates' ] = array(
					'title'   => 'Updates',
					'message' => _wpsf__( 'WordPress Core has an update available.' ),
					'href'    => $this->loadWp()->getAdminUrl_Updates( true ),
					'action'  => sprintf( 'Go To %s', _wpsf__( 'Updates' ) ),
					'rec'     => _wpsf__( 'Updates should be applied as early as possible.' )
				);
			}
		}

		{// autoupdates
			if ( !$oWp->canCoreUpdateAutomatically() ) {
				$aNotices[ 'messages' ][ 'updates_auto' ] = array(
					'title'   => 'Auto Updates',
					'message' => _wpsf__( 'WordPress does not automatically install updates.' ),
					'href'    => $this->getConn()->getModule( 'autoupdates' )->getUrl_AdminPage(),
					'action'  => sprintf( 'Go To %s', _wpsf__( 'Options' ) ),
					'rec'     => _wpsf__( 'Minor WordPress upgrades should be applied automatically.' )
				);
			}
		}

		$aNotices[ 'count' ] = count( $aNotices[ 'messages' ] );
		return $aNotices;
	}

	/**
	 * @return array
	 */
	protected function getNotes() {
		/** @var ICWP_WPSF_Processor_Plugin $oProc */
		$oProc = $this->getConn()->getModule( 'plugin' )->getProcessor();

		$oRetriever = $oProc->getSubProcessorNotes()
							->getQuerySelector();
		/** @var stdClass[] $aNotes */
		$aNotes = $oRetriever->setLimit( 10 )
							 ->setResultsAsVo( false )
							 ->query();

		$oWP = $this->loadWp();
		foreach ( $aNotes as $oItem ) {
			$oItem->created_at = $oWP->getTimeStampForDisplay( $oItem->created_at );
			$oItem->note = stripslashes( sanitize_text_field( $oItem->note ) );
			$oItem->wp_username = sanitize_text_field( $oItem->wp_username );
		}

		return $aNotes;
	}

	/**
	 * @return array[]
	 */
	protected function getStats() {
		$oConn = $this->getConn();
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oModUsers */
		$oModUsers = $oConn->getModule( 'user_management' );
		/** @var ICWP_WPSF_Processor_UserManagement $oProUsers */
		$oProUsers = $oModUsers->getProcessor();
		/** @var ICWP_WPSF_Processor_Statistics $oStats */
		$oStats = $oConn->getModule( 'statistics' )->getProcessor();

		/** @var ICWP_WPSF_Processor_Ips $oIPs */
		$oIPs = $oConn->getModule( 'ips' )->getProcessor();

		$aStats = $oStats->getInsightsStats();
		return array(
			'login'          => array(
				'title'   => _wpsf__( 'Login Blocks' ),
				'val'     => $aStats[ 'login.blocked.all' ],
				'tooltip' => _wpsf__( 'Total login attempts blocked.' )
			),
			'firewall'       => array(
				'title'   => _wpsf__( 'Firewall Blocks' ),
				'val'     => $aStats[ 'firewall.blocked.all' ],
				'tooltip' => _wpsf__( 'Total requests blocked by firewall rules.' )
			),
			'comments'       => array(
				'title'   => _wpsf__( 'Comment Blocks' ),
				'val'     => $aStats[ 'comments.blocked.all' ],
				'tooltip' => _wpsf__( 'Total SPAM comments blocked.' )
			),
			//			'sessions'       => array(
			//				'title'   => _wpsf__( 'Active Sessions' ),
			//				'val'     => $oProUsers->getProcessorSessions()->getCountActiveSessions(),
			//				'tooltip' => _wpsf__( 'Currently active user sessions.' )
			//			),
			'transgressions' => array(
				'title'   => _wpsf__( 'Transgressions' ),
				'val'     => $aStats[ 'ip.transgression.incremented' ],
				'tooltip' => _wpsf__( 'Total transgression against the site.' )
			),
			'ip_blocks'      => array(
				'title'   => _wpsf__( 'IP Blocks' ),
				'val'     => $aStats[ 'ip.connection.killed' ],
				'tooltip' => _wpsf__( 'Total connections blocked/killed after too many transgressions.' )
			),
			'blackips'       => array(
				'title'   => _wpsf__( 'Blacklist IPs' ),
				'val'     => $oIPs->getQuerySelector()
								  ->filterByList( ICWP_WPSF_FeatureHandler_Ips::LIST_AUTO_BLACK )
								  ->count(),
				'tooltip' => _wpsf__( 'Current IP addresses with transgressions against the site.' )
			),
			//			'pro'            => array(
			//				'title'   => _wpsf__( 'Pro' ),
			//				'val'     => $this->isPremium() ? _wpsf__( 'Yes' ) : _wpsf__( 'No' ),
			//				'tooltip' => sprintf( _wpsf__( 'Is this site running %s Pro' ), $oConn->getHumanName() )
			//			),
		);
	}

	/**
	 * @return array
	 */
	protected function getRecentEvents() {
		$oConn = $this->getConn();

		$aStats = array();
		foreach ( $oConn->getModules() as $oModule ) {
			/** @var ICWP_WPSF_FeatureHandler_BaseWpsf $oModule */
			$aStats = array_merge( $aStats, $oModule->getInsightsOpts() );
		}

		$oWP = $this->loadWp();
		$aNames = $this->getInsightStatNames();
		foreach ( $aStats as $sStatKey => $nValue ) {
			$aStats[ $sStatKey ] = array(
				'name' => $aNames[ $sStatKey ],
				'val'  => ( $nValue > 0 ) ? $oWP->getTimeStringForDisplay( $nValue ) : _wpsf__( 'Not yet recorded' ),
			);
		}

		return $aStats;
	}

	/**
	 * @return array[]
	 */
	protected function getRecentAuditTrailEntries() {
		/** @var ICWP_WPSF_Processor_AuditTrail $oProc */
		$oProc = $this->getConn()
					  ->getModule( 'audit_trail' )
					  ->getProcessor();
		try {
			$aItems = $oProc->getQuerySelector()
							->setLimit( 20 )
							->query();
		}
		catch ( Exception $oE ) {
			$aItems = array();
		}
		$oWP = $this->loadWp();
		foreach ( $aItems as $oItem ) {
			$oItem->created_at = $oWP->getTimeStringForDisplay( $oItem->created_at );
			$oItem->message = stripslashes( sanitize_text_field( $oItem->message ) );
		}

		return $aItems;
	}

	/**
	 * @return string[]
	 */
	private function getInsightStatNames() {
		return array(
			'insights_test_cron_last_run_at'        => _wpsf__( 'Simple Test Cron' ),
			'insights_last_scan_ufc_at'             => _wpsf__( 'Unrecognised Files Scan' ),
			'insights_last_scan_wcf_at'             => _wpsf__( 'WordPress Core Files Scan' ),
			'insights_last_scan_ptg_at'             => _wpsf__( 'Plugin/Themes Guard Scan' ),
			'insights_last_scan_wpv_at'             => _wpsf__( 'Plugin Vulnerabilities Scan' ),
			'insights_last_2fa_login_at'            => _wpsf__( 'Successful 2-FA Login' ),
			'insights_last_login_block_at'          => _wpsf__( 'Login Block' ),
			'insights_last_register_block_at'       => _wpsf__( 'User Registration Block' ),
			'insights_last_reset-password_block_at' => _wpsf__( 'Reset Password Block' ),
			'insights_last_firewall_block_at'       => _wpsf__( 'Firewall Block' ),
			'insights_last_idle_logout_at'          => _wpsf__( 'Idle Logout' ),
			'insights_last_password_block_at'       => _wpsf__( 'Password Block' ),
			'insights_last_comment_block_at'        => _wpsf__( 'Comment SPAM Block' ),
			'insights_xml_block_at'                 => _wpsf__( 'XML-RPC Block' ),
			'insights_restapi_block_at'             => _wpsf__( 'Anonymous Rest API Block' ),
			'insights_last_transgression_at'        => sprintf( _wpsf__( '%s Transgression' ), $this->getConn()
																									->getHumanName() ),
			'insights_last_ip_block_at'             => _wpsf__( 'IP Connection Blocked' ),
		);
	}
}