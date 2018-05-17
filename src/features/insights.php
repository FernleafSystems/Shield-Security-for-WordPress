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
				'insight_stats'      => $this->getAllInsightsStats(),
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
	 * @return array
	 */
	protected function getAllInsightsStats() {
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
				'val'  => ( $nValue > 0 ) ? $oWP->getTimeStringForDisplay( $nValue ) : _wpsf__( 'Not recorded' ),
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
			'insights_last_scan_ufc_at'       => _wpsf__( 'Unrecognised Files Scan' ),
			'insights_last_scan_wcf_at'       => _wpsf__( 'WordPress Core Files Scan' ),
			'insights_last_scan_ptg_at'       => _wpsf__( 'Plugin/Themes Guard Scan' ),
			'insights_last_scan_wpv_at'       => _wpsf__( 'Plugin Vulnerabilities Scan' ),
			'insights_last_2fa_login_at'      => _wpsf__( 'Successful 2-FA Login' ),
			'insights_last_login_block_at'    => _wpsf__( 'Login Block' ),
			'insights_last_firewall_block_at' => _wpsf__( 'Firewall Block' ),
			'insights_last_idle_logout_at'    => _wpsf__( 'Idle Logout' ),
			'insights_last_password_block_at' => _wpsf__( 'Password Block' ),
			'insights_last_comment_block_at'  => _wpsf__( 'Comment SPAM Block' ),
			'insights_xml_block_at'           => _wpsf__( 'XML-RPC Block' ),
			'insights_restapi_block_at'       => _wpsf__( 'Anonymous Rest API Block' ),
			'insights_last_transgression_at'  => _wpsf__( 'Shield Transgression' ),
			'insights_last_ip_block_at'       => _wpsf__( 'IP Connection Blocked' ),
		);
	}
}