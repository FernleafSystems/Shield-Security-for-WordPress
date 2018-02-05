<?php

if ( class_exists( 'ICWP_WPSF_Processor_Ips', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'basedb.php' );

class ICWP_WPSF_Processor_Ips extends ICWP_WPSF_BaseDbProcessor {

	/* Unused */
	const LIST_MANUAL_WHITE = 'MW';
	const LIST_MANUAL_BLACK = 'MB';
	const LIST_AUTO_BLACK = 'AB';

	/**
	 * @var bool
	 */
	protected $bVisitorIsWhitelisted;

	/**
	 * @param ICWP_WPSF_FeatureHandler_Ips $oFeatureOptions
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Ips $oFeatureOptions ) {
		parent::__construct( $oFeatureOptions, $oFeatureOptions->getIpListsTableName() );
	}

	/**
	 * Resets the object values to be re-used anew
	 */
	public function init() {
		parent::init();

		/** @var ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getFeature();
		$this->setAutoExpirePeriod( $oFO->getAutoExpireTime() );
	}

	/**
	 * @return bool
	 */
	protected function readyToRun() {
		return ( parent::readyToRun() && $this->loadIpService()->isValidIp_PublicRemote( $this->ip() ) );
	}

	/**
	 */
	public function run() {
		if ( !$this->readyToRun() ) {
			return;
		}

		$this->processBlacklist();

		/** @var ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getFeature();
		add_filter( $oFO->prefix( 'visitor_is_whitelisted' ), array( $this, 'fGetIsVisitorWhitelisted' ), 1000 );

		if ( $oFO->getIsAutoBlackListFeatureEnabled() ) {
			add_filter( $oFO->prefix( 'firewall_die_message' ), array( $this, 'fAugmentFirewallDieMessage' ) );
			add_action( $oFO->prefix( 'pre_plugin_shutdown' ), array( $this, 'action_blackMarkIp' ) );
			add_action( 'wp_login_failed', array( $this, 'doBlackMarkIp' ), 10, 0 );
		}

		add_filter( 'authenticate', array( $this, 'addLoginFailedWarningMessage' ), 10000, 1 );
		add_filter( $oFO->prefix( 'has_permission_to_manage' ), array( $this, 'fGetIsVisitorWhitelisted' ) );
		add_action( 'wp', array( $this, 'doTrack404' ) );
	}

	public function doBlackMarkIp() {
		add_filter( $this->getFeature()->prefix( 'ip_black_mark' ), '__return_true' );
	}

	public function doTrack404() {
		/** @var ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getFeature();
		if ( $oFO->is404Tracking() && is_404() ) {
			if ( $oFO->getOptTracking404() == 'assign-transgression' ) {
				$this->doBlackMarkIp();
			}
			$this->addToAuditEntry(
				sprintf( _wpsf__( '404 detected at "%s"' ), $this->loadDataProcessor()->getRequestPath() ),
				2, 'request_tracking_404'
			);
		}
	}

	/**
	 * @param WP_User|WP_Error $oUserOrError
	 * @return WP_User|WP_Error
	 */
	public function addLoginFailedWarningMessage( $oUserOrError ) {
		if ( $this->loadWp()->isRequestUserLogin() && is_wp_error( $oUserOrError ) ) {
			$oUserOrError->add(
				$this->getFeature()->prefix( 'transgression-warning' ),
				$this->getFeature()->getTextOpt( 'text_loginfailed' )
			);
		}
		return $oUserOrError;
	}

	/**
	 * @param array $aNoticeAttributes
	 */
	public function addNotice_visitor_whitelisted( $aNoticeAttributes ) {

		if ( $this->getController()->getIsPage_PluginAdmin() && $this->getIsVisitorWhitelisted() ) {#
			$oCon = $this->getController();
			$aRenderData = array(
				'notice_attributes' => $aNoticeAttributes,
				'strings'           => array(
					'title'             => sprintf( _wpsf__( '%s is ignoring you' ), $oCon->getHumanName() ),
					'your_ip'           => sprintf( _wpsf__( 'Your IP address is: %s' ), $this->ip() ),
					'notice_message'    => _wpsf__( 'Your IP address is whitelisted and NO features you activate apply to you.' ),
					'including_message' => _wpsf__( 'Including the hiding the WP Login page.' )
				)
			);
			$this->insertAdminNotice( $aRenderData );
		}
	}

	/**
	 * @return array
	 */
	public function getAllValidLists() {
		return array(
			ICWP_WPSF_FeatureHandler_Ips::LIST_AUTO_BLACK,
			ICWP_WPSF_FeatureHandler_Ips::LIST_MANUAL_WHITE,
			ICWP_WPSF_FeatureHandler_Ips::LIST_MANUAL_BLACK
		);
	}

	/**
	 * @param string $sIp
	 * @return boolean
	 */
	protected function isValidIpOrRange( $sIp ) {
		$oIP = $this->loadIpService();
		return $oIP->isValidIp_PublicRemote( $sIp ) || $oIP->isValidIpRange( $sIp );
	}

	/**
	 * @param array $aMessages
	 * @return array
	 */
	public function fAugmentFirewallDieMessage( $aMessages ) {
		if ( !is_array( $aMessages ) ) {
			$aMessages = array();
		}
		$aMessages[] = sprintf( '<p>%s</p>', $this->getTextOfRemainingTransgressions() );
		return $aMessages;
	}

	/**
	 * @param WP_User|WP_Error $oUserOrError
	 * @param string           $sUsername
	 * @return WP_User|WP_Error
	 */
	public function verifyIfAuthenticationValid( $oUserOrError, $sUsername ) {
		// Don't concern yourself if visitor is whitelisted
		if ( $this->getIsVisitorWhitelisted() ) {
			return $oUserOrError;
		}

		$bBlackMark = false;
		$oWp = $this->loadWp();
		if ( $oWp->isRequestUserLogin() ) {

			// If there's an attempt to login with a non-existent username
			if ( !empty( $sUsername ) && !in_array( $sUsername, $oWp->getAllUserLoginUsernames() ) ) {
				$bBlackMark = true;
			}
			else {
				// If the login failed.
				$bUserLoginSuccess = is_object( $oUserOrError ) && ( $oUserOrError instanceof WP_User );
				if ( !$bUserLoginSuccess ) {
					$bBlackMark = true;
				}
			}
		}

		if ( $bBlackMark ) {
			$this->doBlackMarkIp();

			if ( !is_wp_error( $oUserOrError ) ) {
				$oUserOrError = new WP_Error();
			}
			$oUserOrError->add( 'wpsf-autoblacklist', $this->getTextOfRemainingTransgressions() );
		}

		return $oUserOrError;
	}

	/**
	 * @return string
	 */
	private function getTextOfRemainingTransgressions() {
		return sprintf(
			$this->getFeature()->getTextOpt( 'text_remainingtrans' ),
			$this->getRemainingTransgressionsForIp() - 1 // we take one off because it hasn't been incremented at this stage
		);
	}

	/**
	 * @param string $sIp
	 * @return string
	 */
	protected function getRemainingTransgressionsForIp( $sIp = '' ) {
		/** @var ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getFeature();
		if ( empty( $sIp ) ) {
			$sIp = $this->ip();
		}
		return $oFO->getOptTransgressionLimit() - $this->getCurrentTransgressionsForIp( $sIp );
	}

	/**
	 * @param string $sIp
	 * @return int
	 */
	protected function getCurrentTransgressionsForIp( $sIp ) {
		if ( empty( $sIp ) ) {
			$sIp = $this->ip();
		}
		$aData = $this->getIpHasTransgressions( $sIp, true );
		return empty( $aData ) ? 0 : $aData[ 'transgressions' ];
	}

	protected function processBlacklist() {

		// white list rules
		if ( $this->getIsVisitorWhitelisted() ) {
			return;
		}

		/** @var ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getFeature();
		$sIp = $this->ip();
		$bKill = false; // Manual black list first.

		// now try auto black list
		if ( !$bKill && $oFO->getIsAutoBlackListFeatureEnabled() ) {
			$bKill = $this->getIsIpAutoBlackListed( $sIp );
		}

		if ( $bKill ) {
			$sAuditMessage = sprintf( _wpsf__( 'Visitor was found to be on the Black List with IP address "%s" and their connection was killed.' ), $sIp );
			$this->addToAuditEntry( $sAuditMessage, 3, 'black_list_connection_killed' );
			$this->doStatIncrement( 'ip.connection.killed' );

			$this->query_updateLastAccessForAutoBlackListIp( $sIp );

			$this->loadWp()
				 ->wpDie(
					 '<h3>'.sprintf( _wpsf__( 'You have been black listed by the %s plugin.' ),
						 '<a href="https://wordpress.org/plugins/wp-simple-firewall/" target="_blank">'.$this->getController()
																											 ->getHumanName().'</a>'
					 ).'</h3>'
					 .'<br />'.sprintf( _wpsf__( 'You tripped the security plugin defenses a total of %s times making you a suspect.' ), $oFO->getOptTransgressionLimit() )
					 .'<br />'.sprintf( _wpsf__( 'If you believe this to be in error, please contact the site owner.' ) )
					 .'<p>'.sprintf( _wpsf__( 'Time remaining until you are automatically removed from the black list: %s minute(s)' ), floor( $oFO->getAutoExpireTime()/60 ) )
					 .'<br />'._wpsf__( 'If you attempt to access the site within this period the counter will be reset.' )
					 .'</p>'
				 );
		}
	}

	/**
	 * @return boolean
	 */
	protected function getIsVisitorWhitelisted() {
		return apply_filters( $this->getFeature()->prefix( 'visitor_is_whitelisted' ), false );
	}

	/**
	 */
	public function action_blackMarkIp() {
		$this->blackMarkCurrentVisitor();
	}

	/**
	 */
	protected function blackMarkCurrentVisitor() {
		/** @var ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getFeature();

		// Never black mark IPs that are on the whitelist
		if ( $oFO->isPluginDeleting() || !$oFO->getIsAutoBlackListFeatureEnabled()
			 || $this->getIsVisitorWhitelisted() ) {
			return;
		}

		$bDoBlackMark = apply_filters( $oFO->prefix( 'ip_black_mark' ), false );
		if ( $bDoBlackMark ) {
			$this->blackMarkIp( $this->ip() );
		}
	}

	/**
	 * @param string $sIp
	 */
	protected function blackMarkIp( $sIp ) {

		$aIpBlackListData = $this->getIpHasTransgressions( $sIp, true );
		if ( count( $aIpBlackListData ) > 0 ) {
			$this->query_updateBmCounterForIp( $aIpBlackListData );
			$sAuditMessage = sprintf(
				_wpsf__( 'Auto Black List transgression counter was incremented from %s to %s.' ),
				$aIpBlackListData[ 'transgressions' ],
				$aIpBlackListData[ 'transgressions' ] + 1
			);
			$this->addToAuditEntry( $sAuditMessage, 2, 'transgression_counter_increment' );
		}
		else {
			$this->query_addNewAutoBlackListIp( $sIp );
			$sAuditMessage = sprintf(
				_wpsf__( 'Auto Black List transgression counter was started for visitor.' ),
				$sIp
			);
			$this->addToAuditEntry( $sAuditMessage, 2, 'transgression_counter_started' );
		}
		$this->doStatIncrement( 'ip.transgression.incremented' );
	}

	/**
	 * @param boolean $bIsWhitelisted
	 * @return boolean
	 */
	public function fGetIsVisitorWhitelisted( $bIsWhitelisted ) {
		if ( !isset( $this->bVisitorIsWhitelisted ) ) {
			$this->bVisitorIsWhitelisted = $this->getIsIpOnWhiteList( $this->ip() );
		}
		return ( $bIsWhitelisted || $this->bVisitorIsWhitelisted ); //so we still support the legacy lists
	}

	/**
	 * @param string $sIp
	 * @param bool   $bReturnListData
	 * @return bool|array
	 */
	public function getIsIpOnWhiteList( $sIp, $bReturnListData = false ) {

		$aIpData = $this->getIpListData( $sIp, array( ICWP_WPSF_FeatureHandler_Ips::LIST_MANUAL_WHITE ) );
		$bOnList = count( $aIpData ) > 0;

		return ( $bOnList && $bReturnListData ) ? $aIpData : $bOnList;
	}

	/**
	 * @param string $sIp
	 * @param bool   $bReturnListData
	 * @return bool|array
	 */
	public function getIsIpOnBlackLists( $sIp, $bReturnListData = false ) {

		$aIpData = $this->getIpListData( $sIp, array(
			ICWP_WPSF_FeatureHandler_Ips::LIST_AUTO_BLACK,
			ICWP_WPSF_FeatureHandler_Ips::LIST_MANUAL_BLACK
		) );
		$bOnList = count( $aIpData ) > 0;

		return ( $bOnList && $bReturnListData ) ? $aIpData : $bOnList;
	}

	/**
	 * @param string $sIp
	 * @param bool   $bReturnListData
	 * @return bool|array
	 */
	public function getIsIpOnManualBlackList( $sIp, $bReturnListData = false ) {

		$aIpData = $this->getIpListData( $sIp, array( ICWP_WPSF_FeatureHandler_Ips::LIST_MANUAL_BLACK ) );
		$bOnList = count( $aIpData ) > 0;

		return ( ( $bOnList && $bReturnListData ) ? $aIpData : $bOnList );
	}

	/**
	 * The auto black list isn't a simple lookup, but rather has an auto expiration and a transgression count
	 * @param string $sIp
	 * @param bool   $bReturnListData
	 * @return bool|array - will return the associative array of the single row data
	 */
	public function getIsIpAutoBlackListed( $sIp, $bReturnListData = false ) {
		/** @var ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getFeature();

		$nSinceTimeToConsider = $this->time() - $oFO->getAutoExpireTime();
		$nTransgressions = $oFO->getOptTransgressionLimit();

		$aIpData = $this->query_getAutoBlackListDataForIp( $sIp, $nSinceTimeToConsider, $nTransgressions );
		return ( $bReturnListData ? $aIpData : !empty( $aIpData ) );
	}

	/**
	 * The auto black list isn't a simple lookup, but rather has an auto expiration and a transgression count
	 * @param string $sIp
	 * @param bool   $bReturnListData
	 * @return bool|array - will return the associative array of the single row data
	 */
	public function getIpHasTransgressions( $sIp, $bReturnListData = false ) {
		/** @var ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getFeature();

		$nSinceTimeToConsider = $this->time() - $oFO->getAutoExpireTime();

		$aIpData = $this->query_getAutoBlackListDataForIp( $sIp, $nSinceTimeToConsider, 0 );
		return ( $bReturnListData ? $aIpData : !empty( $aIpData ) );
	}

	/**
	 * @return array
	 */
	public function getWhitelistData() {
		$aData = $this->query_getListData( array( ICWP_WPSF_FeatureHandler_Ips::LIST_MANUAL_WHITE ) );
		return $aData;
	}

	/**
	 * @return array
	 */
	public function getAutoBlacklistData() {
		$aData = $this->query_getListData( array( ICWP_WPSF_FeatureHandler_Ips::LIST_AUTO_BLACK ) );
		return $aData;
	}

	/**
	 * @param string $sIp
	 * @param array  $aLists
	 * @return array
	 */
	public function getIpListData( $sIp, $aLists ) {

		$aData = array();

		$aResult = $this->query_getListData( $aLists );
		foreach ( $aResult as $aRow ) {
			try {
				if ( $this->loadIpService()->checkIp( $sIp, $aRow[ 'ip' ] ) ) {
					$aData[] = $aRow;
				}
			}
			catch ( Exception $oE ) {
			}
		}

		return $aData;
	}

	/**
	 * @param string $sIp
	 * @param string $sLabel
	 * @return bool|int
	 */
	public function addIpToWhiteList( $sIp, $sLabel = '' ) {
		$bSuccess = false;
		$sIp = trim( $sIp );
		if ( $this->isValidIpOrRange( $sIp ) ) {

			$aIpData = $this->query_getIpWhiteListData( $sIp );
			if ( empty( $aIpData ) ) {
				$aIpData = $this->query_addNewManualWhiteListIp( $sIp, $sLabel );
			}
			else if ( $sLabel != $aIpData[ 'label' ] ) {
				$this->query_updateIpRecordLabel( $sLabel, $aIpData );
			}
			$bSuccess = !empty( $aIpData ) && is_array( $aIpData );
		}
		return $bSuccess;
	}

	public function removeIpFromList( $sIp, $sList ) {
		return $this->query_deleteIpFromList( $sIp, $sList );
	}

	/**
	 * @param string $sIp
	 * @param string $sLabel
	 * @return array|bool|int
	 */
	protected function query_addNewManualWhiteListIp( $sIp, $sLabel = '' ) {

		// Now add new entry
		$aNewData = array();
		$aNewData[ 'ip' ] = $sIp;
		$aNewData[ 'label' ] = empty( $sLabel ) ? _wpsf__( 'No Label' ) : $sLabel;
		$aNewData[ 'list' ] = ICWP_WPSF_FeatureHandler_Ips::LIST_MANUAL_WHITE;
		$aNewData[ 'ip6' ] = $this->loadIpService()->getIpVersion( $sIp ) == 6;
		$aNewData[ 'transgressions' ] = 0;
		$aNewData[ 'is_range' ] = strpos( $sIp, '/' ) !== false;
		$aNewData[ 'last_access_at' ] = 0;
		$aNewData[ 'created_at' ] = $this->time();

		$mResult = $this->insertData( $aNewData );
		return $mResult ? $aNewData : $mResult;
	}

	/**
	 * @param string $sIp
	 * @return array|bool|int
	 */
	protected function query_addNewAutoBlackListIp( $sIp ) {

		// Ensure we delete any previous old entries as we go.
		$this->query_deleteIpFromList( $sIp, ICWP_WPSF_FeatureHandler_Ips::LIST_AUTO_BLACK );

		// Now add new entry
		$aNewData = array();
		$aNewData[ 'ip' ] = $sIp;
		$aNewData[ 'label' ] = 'auto';
		$aNewData[ 'list' ] = ICWP_WPSF_FeatureHandler_Ips::LIST_AUTO_BLACK;
		$aNewData[ 'ip6' ] = $this->loadIpService()->getIpVersion( $sIp ) == 6;
		$aNewData[ 'transgressions' ] = 1;
		$aNewData[ 'is_range' ] = 0;
		$aNewData[ 'last_access_at' ] = $this->time();
		$aNewData[ 'created_at' ] = $this->time();

		$mResult = $this->insertData( $aNewData );
		return $mResult ? $aNewData : $mResult;
	}

	/**
	 * @param array $aCurrentData
	 * @return bool|int
	 */
	protected function query_updateBmCounterForIp( $aCurrentData ) {
		$aUpdated = array(
			'transgressions' => $aCurrentData[ 'transgressions' ] + 1,
			'last_access_at' => $this->time(),
		);
		return $this->updateRowsWhere( $aUpdated, $aCurrentData );
	}

	/**
	 * @param string $sLabel
	 * @param array  $aCurrentData
	 * @return bool|int
	 */
	protected function query_updateIpRecordLabel( $sLabel, $aCurrentData ) {
		$aUpdated = array( 'label' => $sLabel );
		return $this->updateRowsWhere( $aUpdated, $aCurrentData );
	}

	/**
	 * @param string $sIp
	 * @return bool|int
	 */
	protected function query_updateLastAccessForAutoBlackListIp( $sIp ) {
		$aCurrentData = array(
			'ip'   => $sIp,
			'list' => ICWP_WPSF_FeatureHandler_Ips::LIST_AUTO_BLACK
		);
		$aUpdated = array( 'last_access_at' => $this->time() );
		return $this->updateRowsWhere( $aUpdated, $aCurrentData );
	}

	/**
	 * @param $sIp
	 * @param $sList
	 * @return bool|int
	 */
	protected function query_deleteIpFromList( $sIp, $sList ) {

		$sQuery = "
				DELETE from `%s`
				WHERE
					`ip`		= '%s'
					AND `list`	= '%s'
			";
		$sQuery = sprintf( $sQuery,
			$this->getTableName(),
			esc_sql( $sIp ),
			esc_sql( $sList )
		);
		return $this->loadDbProcessor()->doSql( $sQuery );
	}

	/**
	 * We can be specific with the IP in this query since auto black lists is single IPs only.
	 * @param string $sIp
	 * @return array
	 */
	protected function query_getIpWhiteListData( $sIp ) {

		$sQuery = "
				SELECT *
				FROM `%s`
				WHERE
					`ip`					= '%s'
					AND `list`				= '%s'
					AND `deleted_at`		= 0
			";

		$sQuery = sprintf( $sQuery,
			$this->getTableName(),
			esc_sql( $sIp ),
			ICWP_WPSF_FeatureHandler_Ips::LIST_MANUAL_WHITE
		);
		$mResult = $this->selectCustom( $sQuery );
		return ( is_array( $mResult ) && isset( $mResult[ 0 ] ) ) ? $mResult[ 0 ] : array();
	}

	/**
	 * We can be specific with the IP in this query since auto black lists is single IPs only.
	 * @param string $sIp
	 * @param int    $nSince
	 * @param int    $nTransgressionLimit
	 * @return array
	 */
	protected function query_getAutoBlackListDataForIp( $sIp, $nSince = 0, $nTransgressionLimit = 0 ) {

		$sQuery = "
				SELECT *
				FROM `%s`
				WHERE
					`ip`					= '%s'
					AND `list`				= '%s'
					AND `transgressions`	>= '%s'
					AND `last_access_at`	>= %s
					AND `deleted_at`		= 0
			";

		$sQuery = sprintf( $sQuery,
			$this->getTableName(),
			esc_sql( $sIp ),
			ICWP_WPSF_FeatureHandler_Ips::LIST_AUTO_BLACK,
			esc_sql( $nTransgressionLimit ),
			esc_sql( $nSince )
		);
		$mResult = $this->selectCustom( $sQuery );
		return ( is_array( $mResult ) && isset( $mResult[ 0 ] ) ) ? $mResult[ 0 ] : array();
	}

	/**
	 * @param array $aLists
	 * @return array
	 */
	protected function query_getListData( $aLists ) {

		if ( !is_array( $aLists ) ) {
			$aLists = array( $aLists );
		}

		$sQuery = "
				SELECT *
				FROM `%s`
				WHERE
					`list`			IN ( %s )
					AND `deleted_at`	= 0
			";

		$sQuery = sprintf( $sQuery,
			$this->getTableName(),
			sprintf( "'%s'", implode( "','", $aLists ) )
		);
		$mResult = $this->selectCustom( $sQuery );
		return is_array( $mResult ) ? $mResult : array();
	}

	/**
	 * @return string
	 */
	public function getCreateTableSql() {
		$sSqlTables = "CREATE TABLE %s (
				id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
				ip varchar(40) NOT NULL DEFAULT '',
				label varchar(255) NOT NULL DEFAULT '',
				list varchar(4) NOT NULL DEFAULT '',
				ip6 tinyint(1) NOT NULL DEFAULT 0,
				is_range tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
				transgressions tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
				last_access_at int(15) UNSIGNED NOT NULL DEFAULT 0,
				created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
				deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY  (id)
			) %s;";
		return sprintf( $sSqlTables, $this->getTableName(), $this->loadDbProcessor()->getCharCollate() );
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		$aDef = $this->getFeature()->getDefinition( 'ip_list_table_columns' );
		return ( is_array( $aDef ) ? $aDef : array() );
	}

	/**
	 * @param int $nTimeStamp
	 * @return bool|int
	 */
	protected function deleteAllRowsOlderThan( $nTimeStamp ) {
		$sQuery = "
				DELETE from `%s`
				WHERE
					`last_access_at`	< %s
					AND `list`			= '%s'
			";
		$sQuery = sprintf( $sQuery,
			$this->getTableName(),
			esc_sql( $nTimeStamp ),
			ICWP_WPSF_FeatureHandler_Ips::LIST_AUTO_BLACK
		);
		return $this->loadDbProcessor()->doSql( $sQuery );
	}
}