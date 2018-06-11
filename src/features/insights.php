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

		$aRecentAuditTrail = $this->getRecentAuditTrailEntries();
		$aSecNotices = $this->getNotices();
		$aNotes = $this->getNotes();

		$nNoticesCount = 0;
		foreach ( $aSecNotices as $aNoticeSection ) {
			$nNoticesCount += isset( $aNoticeSection[ 'count' ] ) ? $aNoticeSection[ 'count' ] : 0;
		}

		$aData = array(
			'vars'    => array(
				'summary'               => $this->getInsightsModsSummary(),
				'audit_trail_recent'    => $aRecentAuditTrail,
				'insight_events'        => $this->getRecentEvents(),
				'insight_notices'       => $aSecNotices,
				'insight_notices_count' => $nNoticesCount,
				'insight_stats'         => $this->getStats(),
				'insight_notes'         => $aNotes,
			),
			'inputs'  => array(
				'license_key' => array(
					'name'      => $this->prefixOptionKey( 'license_key' ),
					'maxlength' => $this->getDef( 'license_key_length' ),
				)
			),
			'ajax'    => array(
				'admin_note_new'     => $this->getAjaxActionData( 'admin_note_new' ),
				'admin_notes_render' => $this->getAjaxActionData( 'admin_notes_render' ),
				'admin_notes_delete' => $this->getAjaxActionData( 'admin_notes_delete' ),
			),
			'hrefs'   => array(
				'shield_pro_url'           => 'https://icwp.io/shieldpro',
				'shield_pro_more_info_url' => 'https://icwp.io/shld1',
			),
			'flags'   => array(
				'has_audit_trail_entries' => !empty( $aRecentAuditTrail ),
				'show_ads'                => false,
				'show_standard_options'   => false,
				'show_alt_content'        => true,
				'is_pro'                  => $this->isPremium(),
				'has_notices'             => count( $aSecNotices ) > 0,
				'has_notes'               => count( $aNotes ) > 0,
				'can_notes'               => $this->isPremium() //not the way to determine
			),
			'strings' => $this->getDisplayStrings(),
		);
		echo $this->renderTemplate( '/wpadmin_pages/insights/index.twig', $aData, true );
	}

	public function insertCustomJsVars() {

		if ( $this->isThisModulePage() ) {
			wp_localize_script(
				$this->prefix( 'plugin' ),
				'icwp_wpsf_vars_insights',
				array(
					'ajax_admin_notes_render' => $this->getAjaxActionData( 'admin_notes_render' ),
					'ajax_admin_notes_delete' => $this->getAjaxActionData( 'admin_notes_delete' ),
				)
			);
		}
	}

	/**
	 * @param array $aAjaxResponse
	 * @return array
	 */
	public function handleAuthAjax( $aAjaxResponse ) {

		if ( empty( $aAjaxResponse ) ) {
			switch ( $this->loadDP()->request( 'exec' ) ) {

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
		$oDP = $this->loadDP();
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getConn()->getModule( 'plugin' );
		$sNote = trim( $oDP->post( 'admin_note', '' ) );

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
						   ->getQueryCreator()
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
		$oDP = $this->loadDP();
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getConn()->getModule( 'plugin' );
		/** @var ICWP_WPSF_Processor_Plugin $oP */
		$oP = $oMod->getProcessor();

		$nNoteId = (int)trim( $oDP->post( 'note_id', 0 ) );
		if ( $nNoteId >= 0 ) {
			$oP->getSubProcessorNotes()
			   ->getQueryDeleter()
			   ->delete( $nNoteId );
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
			'/wpadmin_pages/insights/admin_notes_table.twig',
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
		$oDp = $this->loadDP();
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
					$nTimeLeft = ( $nExpiresAt - $oDp->time() );
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
			$nCount = 0;
			$aActivePlugs = $oWpPlugins->getActivePlugins();
			foreach ( $oWpPlugins->getPlugins() as $sFile => $aPlugData ) {
				if ( !in_array( $sFile, $aActivePlugs ) ) {
					$nCount++;
				}
			}
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
							->getQueryRetriever();
		$aNotes = $oRetriever->setLimit( 10 )
							 ->setResultsAsVo( false )
							 ->all();

		$oWP = $this->loadWp();
		foreach ( $aNotes as $oItem ) {
			$oItem->created_at = $oWP->getTimeStringForDisplay( $oItem->created_at );
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
		/** @var ICWP_WPSF_Processor_Statistics $oStats */
		$oStats = $oConn->getModule( 'statistics' )->getProcessor();

		/** @var ICWP_WPSF_Processor_Ips $oIPs */
		$oIPs = $oConn->getModule( 'ips' )->getProcessor();

		$aStats = $oStats->getInsightsStats();
		return array(
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
			'sessions'       => array(
				'title'   => _wpsf__( 'Active Sessions' ),
				'val'     => count( $oModUsers->getActiveSessionsData() ),
				'tooltip' => _wpsf__( 'Currently active user sessions.' )
			),
			'blackips'       => array(
				'title'   => _wpsf__( 'Blacklist IPs' ),
				'val'     => count( $oIPs->getAutoBlacklistData() ),
				'tooltip' => _wpsf__( 'Current IP addresses with transgressions against the site.' )
			),
			'pro'            => array(
				'title'   => _wpsf__( 'Pro' ),
				'val'     => $this->isPremium() ? _wpsf__( 'Yes' ) : _wpsf__( 'No' ),
				'tooltip' => sprintf( _wpsf__( 'Is this site running %s Pro' ), $oConn->getHumanName() )
			),
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
			$aItems = $oProc->getAuditTrailFinder()
							->setLimit( 20 )
							->all();
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
			'insights_last_transgression_at'        => sprintf( _wpsf__( '%s Transgression' ), $this->getConn()->getHumanName() ),
			'insights_last_ip_block_at'             => _wpsf__( 'IP Connection Blocked' ),
		);
	}
}