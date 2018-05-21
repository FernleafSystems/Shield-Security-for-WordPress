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
		$oWp = $this->loadWp();

		$aRecentAuditTrail = $this->getRecentAuditTrailEntries();
		$aSecNotices = $this->getNotices();
		$aData = array(
			'vars'    => array(
				'activation_url'        => $oWp->getHomeUrl(),
				'summary'               => $this->getInsightsModsSummary(),
				'audit_trail_recent'    => $aRecentAuditTrail,
				'insight_events'        => $this->getRecentEvents(),
				'insight_notices'       => $aSecNotices,
				'insight_notices_count' => count( $aSecNotices ),
				'insight_stats'         => $this->getStats(),
			),
			'inputs'  => array(
				'license_key' => array(
					'name'      => $this->prefixOptionKey( 'license_key' ),
					'maxlength' => $this->getDef( 'license_key_length' ),
				)
			),
			'ajax'    => array(
				'license_handling' => $this->getAjaxActionData( 'license_handling' ),
				'connection_debug' => $this->getAjaxActionData( 'connection_debug' )
			),
			'aHrefs'  => array(
				'shield_pro_url'           => 'http://icwp.io/shieldpro',
				'shield_pro_more_info_url' => 'http://icwp.io/shld1',
				'iframe_url'               => $this->getDef( 'landing_page_url' ),
				'keyless_cp'               => $this->getDef( 'keyless_cp' ),
			),
			'flags'   => array(
				'has_audit_trail_entries' => !empty( $aRecentAuditTrail ),
				'show_ads'                => false,
				'show_standard_options'   => false,
				'show_alt_content'        => true,
				'is_pro'                  => $this->isPremium(),
				'has_notices'             => count( $aSecNotices ) > 0
			),
			'strings' => $this->getDisplayStrings(),
		);
		echo $this->renderTemplate( '/wpadmin_pages/insights/index.twig', $aData, true );
	}

	/**
	 * @return array
	 */
	protected function getDisplayStrings() {
		return $this->loadDP()->mergeArraysRecursive(
			parent::getDisplayStrings(),
			array(
				'recommendation' => ucfirst( _wpsf__( 'recommendation' ) ),
				'suggestion'     => ucfirst( _wpsf__( 'suggestion' ) ),

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
	 * @return string[]
	 */
	protected function getNotices() {
		return array(
			'site'    => $this->getNoticesSite(),
			'shield'  => $this->getNoticesShield(),
			'scans'   => $this->getNoticesScans(),
			'plugins' => $this->getNoticesPlugins(),
			'themes'  => $this->getNoticesThemes(),
			'core'    => $this->getNoticesCore(),
			'user'    => $this->getNoticesUsers(),
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

		$aNotices[ 'count' ] = count( $aNotices[ 'messages' ] );
		return $aNotices;
	}

	/**
	 * @return array
	 */
	protected function getNoticesUsers() {
		$oWpUsers = $this->loadWpUsers();

		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oModUsers */
		$oModUsers = $this->getConn()->getModule( 'user_management' );

		$aNotices = array(
			'title'    => _wpsf__( 'Users' ),
			'messages' => array()
		);

		{ //admin user
			$oAdmin = $oWpUsers->getUserByUsername( 'admin' );
			if ( !empty( $oAdmin ) && user_can( $oAdmin, 'manage_options' ) ) {
				$aNotices[ 'messages' ][ 'admin' ] = array(
					'title'   => 'Admin User',
					'message' => sprintf( _wpsf__( "Default 'admin' user still available." ) ),
					'href'    => '',
					'rec'     => _wpsf__( "Default 'admin' user should be disabled or removed." )
				);
			}
		}

		{//password policies
			if ( !$oModUsers->isPasswordPoliciesEnabled() ) {
				$aNotices[ 'messages' ][ 'password' ] = array(
					'title'   => 'Password Policies',
					'message' => _wpsf__( "Strong password policies are not enforced." ),
					'href'    => $oModUsers->getUrl_AdminPage(),
					'action'  => sprintf( 'Go To %s', _wpsf__( 'Options' ) ),
					'rec'     => _wpsf__( 'Password policies should be turned-on.' )
				);
			}
		}

		$aNotices[ 'count' ] = count( $aNotices[ 'messages' ] );
		return $aNotices;
	}

	/**
	 * @return array
	 */
	protected function getNoticesShield() {

		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oModSecAdmin */
		$oModSecAdmin = $this->getConn()->getModule( 'admin_access_restriction' );

		$aNotices = array(
			'title'    => _wpsf__( 'Shield Security' ),
			'messages' => array()
		);

		{//sec admin
			if ( !( $oModSecAdmin->isModuleEnabled() && $oModSecAdmin->hasAccessKey() ) ) {
				$aNotices[ 'messages' ][ 'sec_admin' ] = array(
					'title'   => 'Security Admin',
					'message' => sprintf(
						_wpsf__( "The Security Admin protection is not active." ),
						$this->getConn()->getHumanName()
					),
					'href'    => $oModSecAdmin->getUrl_AdminPage(),
					'action'  => sprintf( 'Go To %s', _wpsf__( 'Options' ) ),
					'rec'     => _wpsf__( 'Security Admin should be turned-on to protect your security settings.' )
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

		// Inactive
		{
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

		// updates
		{
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

		// Inactive
		{
			$nInactive = count( $oWpT->getThemes() ) - 1;
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

		// updates
		{
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

		// updates
		{
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

		// updates
		{
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

		{ // Disallow file edit
			if ( current_user_can( 'edit_plugins' ) ) { //assumes current user is admin
				$aNotices[ 'messages' ][ 'disallow_file_edit' ] = array(
					'title'   => 'Code Editor',
					'message' => _wpsf__( 'Direct editing of plugin/theme files is permitted.' ),
					'href'    => $this->getConn()->getModule( 'lockdown' )->getUrl_AdminPage(),
					'action'  => sprintf( 'Go To %s', _wpsf__( 'Options' ) ),
					'rec'     => _wpsf__( 'WP Plugin file editing should be disabled.' )
				);
			}
		}

		{ // db password strength
			$this->loadAutoload();
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
	protected function getNoticesScans() {
		$aNotices = array(
			'title'    => _wpsf__( 'Scans' ),
			'messages' => array()
		);

		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oModHg */
		$oModHg = $this->getConn()->getModule( 'hack_protect' );

		// Core files
		{
			if ( !$oModHg->isWcfScanEnabled() ) {
				$aNotices[ 'messages' ][ 'wcf' ] = array(
					'title'   => 'WP Core Files',
					'message' => _wpsf__( 'Core File scanner is not enabled.' ),
					'href'    => $oModHg->getUrl_AdminPage(),
					'action'  => sprintf( 'Go To %s', _wpsf__( 'Options' ) ),
					'rec'     => _wpsf__( 'Automatic WordPress Core File scanner should be turned-on.' )
				);
			}
			else if ( $oModHg->getScanHasProblem( 'wcf' ) ) {
				$aNotices[ 'messages' ][ 'wcf' ] = array(
					'title'   => 'WP Core Files',
					'message' => _wpsf__( 'Modified WordPress core files found.' ),
					'href'    => $oModHg->getUrl_Wizard( 'wcf' ),
					'action'  => _wpsf__( 'Run Scan' ),
					'rec'     => _wpsf__( 'Scan WP core files and repair any files that are flagged as modified.' )
				);
			}
		}

		// Unrecognised
		{
			if ( !$oModHg->isUfcEnabled() ) {
				$aNotices[ 'messages' ][ 'ufc' ] = array(
					'title'   => 'Unrecognised Files',
					'message' => _wpsf__( 'Unrecognised File scanner is not enabled.' ),
					'href'    => $oModHg->getUrl_AdminPage(),
					'action'  => sprintf( 'Go To %s', _wpsf__( 'Options' ) ),
					'rec'     => _wpsf__( 'Automatic scanning for non-WordPress core files is recommended.' )
				);
			}
			else if ( $oModHg->getScanHasProblem( 'ufc' ) ) {
				$aNotices[ 'messages' ][ 'ufc' ] = array(
					'title'   => 'Unrecognised Files',
					'message' => _wpsf__( 'Unrecognised files found in WordPress Core directory.' ),
					'href'    => $oModHg->getUrl_Wizard( 'ufc' ),
					'action'  => _wpsf__( 'Run Scan' ),
					'rec'     => _wpsf__( 'Scan and remove any files that are not meant to be in the WP core directories.' )
				);
			}
		}

		// Plugin/Theme Guard
		{
			if ( !$oModHg->isPtgEnabled() ) {
				$aNotices[ 'messages' ][ 'ptg' ] = array(
					'title'   => 'Plugin/Theme Guard',
					'message' => _wpsf__( 'Automatic Plugin/Themes Guard is not enabled.' ),
					'href'    => $oModHg->getUrl_AdminPage(),
					'action'  => sprintf( 'Go To %s', _wpsf__( 'Options' ) ),
					'rec'     => _wpsf__( 'Automatic detection of plugin/theme modifications is recommended.' )
				);
			}
			else if ( $oModHg->getScanHasProblem( 'ptg' ) ) {
				$aNotices[ 'messages' ][ 'ptg' ] = array(
					'title'   => 'Plugin/Theme Guard',
					'message' => _wpsf__( 'A plugin/theme was found to have been modified.' ),
					'href'    => $oModHg->getUrl_Wizard( 'ptg' ),
					'action'  => _wpsf__( 'Run Scan' ),
					'rec'     => _wpsf__( 'Reviewing modifications to your plugins/themes is recommended.' )
				);
			}
		}

		// Vulnerability Scanner
		{
			if ( !$oModHg->isWpvulnEnabled() ) {
				$aNotices[ 'messages' ][ 'wpv' ] = array(
					'title'   => 'Vulnerability Scanner',
					'message' => _wpsf__( 'Plugin Vulnerability Scanner is not enabled.' ),
					'href'    => $oModHg->getUrl_AdminPage(),
					'action'  => sprintf( 'Go To %s', _wpsf__( 'Options' ) ),
					'rec'     => _wpsf__( 'Automatic detection of plugin vulnerabilities is recommended.' )
				);
			}
			else if ( $oModHg->getScanHasProblem( 'wpv' ) ) {
				$aNotices[ 'messages' ][ 'wpv' ] = array(
					'title'   => 'Vulnerable Plugins',
					'message' => _wpsf__( 'At least 1 plugin has known vulnerabilities.' ),
					'href'    => $this->loadWp()->getAdminUrl_Plugins( true ),
					'action'  => sprintf( 'Go To %s', _wpsf__( 'Plugins' ) ),
					'rec'     => _wpsf__( 'Plugins with known vulnerabilities should be updated, removed, or replaced.' )
				);
			}
		}

		$aNotices[ 'count' ] = count( $aNotices[ 'messages' ] );
		return $aNotices;
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
				'tooltip' => _wpsf__( 'Is this site running Shield Pro' )
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
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sSectionSlug = $aOptionsParams[ 'slug' ];
		switch ( $sSectionSlug ) {

			case 'section_email_options' :
				$sTitle = _wpsf__( 'Email Options' );
				break;

			default:
				throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams[ 'title' ] = $sTitle;
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
			case 'send_email_throttle_limit' :
				$sName = _wpsf__( 'Email Throttle Limit' );
				$sSummary = _wpsf__( 'Limit Emails Per Second' );
				$sDescription = _wpsf__( 'You throttle emails sent by this plugin by limiting the number of emails sent every second. This is useful in case you get hit by a bot attack. Zero (0) turns this off. Suggested: 10' );
				break;

			default:
				throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}

	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {
		$sLimit = $this->getOpt( 'send_email_throttle_limit' );
		if ( !is_numeric( $sLimit ) || $sLimit < 0 ) {
			$sLimit = 0;
		}
		$this->setOpt( 'send_email_throttle_limit', $sLimit );
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
			'insights_last_transgression_at'        => _wpsf__( 'Shield Transgression' ),
			'insights_last_ip_block_at'             => _wpsf__( 'IP Connection Blocked' ),
		);
	}
}