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

		$aData = array(
			'vars'    => array(
				'activation_url'     => $oWp->getHomeUrl(),
				'summary'            => $this->getInsightsModsSummary(),
				'audit_trail_recent' => $aRecentAuditTrail,
				'insight_events'     => $this->getRecentEvents(),
				'insight_notices'    => $this->getNotices(),
				'insight_stats'      => $this->getStats(),
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
			),
			'strings' => $this->getDisplayStrings(),
		);
		echo $this->renderTemplate( '/wpadmin_pages/insights/index.twig', $aData, true );
	}

	/**
	 * @return array[]
	 */
	protected function getInsightsModsSummary() {
		$aMods = array();
		foreach ( $this->getModulesSummaryData() as $aMod ) {
			if ( !in_array( $aMod[ 'slug' ], [ 'plugin', 'insights' ] ) ) {
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
							'href'    => ''
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
					'href'    => ''
				);
			}
		}

		{//password policies
			if ( !$oModUsers->isPasswordPoliciesEnabled() ) {
				$aNotices[ 'messages' ][ 'password' ] = array(
					'title'   => 'Password Policies',
					'message' => _wpsf__( "Strong password policies are not enforced." ),
					'href'    => $oModUsers->getUrl_AdminPage()
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
						_wpsf__( "The Security Admin feature is not active." ),
						$this->getConn()->getHumanName()
					),
					'href'    => $oModSecAdmin->getUrl_AdminPage()
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
					'message' => sprintf( _wpsf__( '%s inactive plugin(s) - which should be removed.' ), $nCount ),
					'href'    => ''
				);
			}
		}

		// updates
		{
			$nCount = count( $oWpPlugins->getUpdates() );
			if ( $nCount > 0 ) {
				$aNotices[ 'messages' ][ 'updates' ] = array(
					'title'   => 'Updates',
					'message' => sprintf( _wpsf__( '%s plugin update(s) - which should be applied.' ), $nCount ),
					'href'    => ''
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
					'message' => sprintf( _wpsf__( '%s inactive themes(s) - which should be removed.' ), $nInactive ),
					'href'    => ''
				);
			}
		}

		// updates
		{
			$nCount = count( $oWpT->getUpdates() );
			if ( $nCount > 0 ) {
				$aNotices[ 'messages' ][ 'updates' ] = array(
					'title'   => 'Updates',
					'message' => sprintf( _wpsf__( '%s theme update(s) - which should be applied.' ), $nCount ),
					'href'    => ''
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
					'message' => _wpsf__( 'WordPress Core update should be applied.' ),
					'href'    => ''
				);
			}
		}

		// updates
		{
			if ( !$oWp->canCoreUpdateAutomatically() ) {
				$aNotices[ 'messages' ][ 'updates_auto' ] = array(
					'title'   => 'Auto Updates',
					'message' => _wpsf__( 'Security updates not applied automatically.' ),
					'href'    => ''
				);
			}
		}

		{ // Disallow file edit
			if ( current_user_can( 'edit_plugins' ) ) { //assumes current user is admin
				$aNotices[ 'messages' ][ 'disallow_file_edit' ] = array(
					'title'   => 'Code Editor',
					'message' => _wpsf__( 'Direct editing of plugin/theme files is permitted.' ),
					'href'    => ''
				);
			}
		}

		{ // db prefix
			if ( in_array( $this->loadDbProcessor()->getPrefix(), array( 'wp_', 'wordpress_' ) ) ) {
				$aNotices[ 'messages' ][ 'db_prefix' ] = array(
					'title'   => 'DB Prefix',
					'message' => _wpsf__( 'WordPress database prefix is the default.' ),
					'href'    => ''
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
					'href'    => ''
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
					'title'   => 'WordCore Files',
					'message' => _wpsf__( 'Automatic WordPress Core File scanner is not enabled.' ),
					'href'    => $oModHg->getUrl_AdminPage()
				);
			}
			else if ( $oModHg->getScanHasProblem( 'wcf' ) ) {
				$aNotices[ 'messages' ][ 'wcf' ] = array(
					'title'   => 'WordCore Files',
					'message' => _wpsf__( 'Modified WordPress core files found.' ),
					'href'    => $oModHg->getUrl_Wizard( 'wcf' )
				);
			}
		}

		// Unrecognised
		{
			if ( !$oModHg->isUfcEnabled() ) {
				$aNotices[ 'messages' ][ 'ufc' ] = array(
					'title'   => 'Unrecognised Files',
					'message' => _wpsf__( 'Automatic Unrecognised File scanner is not enabled.' ),
					'href'    => $oModHg->getUrl_AdminPage()
				);
			}
			else if ( $oModHg->getScanHasProblem( 'ufc' ) ) {
				$aNotices[ 'messages' ][ 'ufc' ] = array(
					'title'   => 'Unrecognised Files',
					'message' => _wpsf__( 'Unrecognised files found in WordPress Core directory.' ),
					'href'    => $oModHg->getUrl_Wizard( 'ufc' )
				);
			}
		}

		// Plugin/Theme Guard
		{
			if ( !$oModHg->isPtgEnabled() ) {
				$aNotices[ 'messages' ][ 'ptg' ] = array(
					'title'   => 'Plugin/Theme Guard',
					'message' => _wpsf__( 'Automatic Plugin/Themes Guard is not enabled.' ),
					'href'    => $oModHg->getUrl_AdminPage()
				);
			}
			else if ( $oModHg->getScanHasProblem( 'ptg' ) ) {
				$aNotices[ 'messages' ][ 'ptg' ] = array(
					'title'   => 'Plugin/Theme Guard',
					'message' => _wpsf__( 'A plugin/theme was found to have been modified.' ),
					'href'    => $oModHg->getUrl_Wizard( 'ptg' )
				);
			}
		}

		// Vulnerability Scanner
		{
			if ( !$oModHg->isWpvulnEnabled() ) {
				$aNotices[ 'messages' ][ 'wpv' ] = array(
					'title'   => 'Vulnerability Scanner',
					'message' => _wpsf__( 'Automatic Vulnerability Scanner is not enabled.' ),
					'href'    => $oModHg->getUrl_AdminPage()
				);
			}
			else if ( $oModHg->getScanHasProblem( 'wpv' ) ) {
				$aNotices[ 'messages' ][ 'wpv' ] = array(
					'title'   => 'Vulnerable Plugins',
					'message' => _wpsf__( 'At least 1 plugin has known vulnerabilities.' ),
					'href'    => ''
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
		/** @var ICWP_WPSF_Processor_Statistics $oStats */
		$oStats = $this->getConn()->getModule( 'statistics' )->getProcessor();

		$aStats = $oStats->getInsightsStats();
		return array(
			'transgressions' => array(
				'title' => _wpsf__( 'Transgressions' ),
				'val'   => $aStats[ 'ip.transgression.incremented' ]
			),
			'ip_blocks'      => array(
				'title' => _wpsf__( 'IP Blocks' ),
				'val'   => $aStats[ 'ip.connection.killed' ]
			),
			'login'          => array(
				'title' => _wpsf__( 'Login Blocks' ),
				'val'   => $aStats[ 'login.blocked.all' ]
			),
			'firewall'       => array(
				'title' => _wpsf__( 'Firewall Blocks' ),
				'val'   => $aStats[ 'firewall.blocked.all' ]
			),
			'comments'       => array(
				'title' => _wpsf__( 'Comment Blocks' ),
				'val'   => $aStats[ 'comments.blocked.all' ]
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

		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oModUsers */
		$oModUsers = $oConn->getModule( 'user_management' );

		$aExtras = array(
			'insights_user_sessions' => array(
				'name' => _wpsf__( 'Active User Sessions' ),
				'val'  => count( $oModUsers->getActiveSessionsData() )
			),
			'insights_is_pro'        => array(
				'name' => _wpsf__( 'Active Pro License' ),
				'val'  => $this->isPremium() ? _wpsf__( 'Yes' ) : _wpsf__( 'No' )
			)
		);

		return array_merge( $aExtras, $aStats );
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
							->setLimit( 10 )
							->all();
		}
		catch ( Exception $oE ) {
			$aItems = array();
		}
		$oWP = $this->loadWp();
		foreach ( $aItems as $oItem ) {
			$oItem->created_at = $oWP->getTimeStringForDisplay( $oItem->created_at );
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