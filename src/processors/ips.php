<?php

if ( !class_exists( 'ICWP_WPSF_Processor_Ips_V1', false ) ):

	require_once( dirname(__FILE__).ICWP_DS.'basedb.php' );

	class ICWP_WPSF_Processor_Ips_V1 extends ICWP_WPSF_BaseDbProcessor {

		const LIST_MANUAL_WHITE =	'MW';
		const LIST_MANUAL_BLACK =	'MB';
		const LIST_AUTO_BLACK =		'AB';

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
		 */
		public function run() {
			/** @var ICWP_WPSF_FeatureHandler_Ips $oFO */
			$oFO = $this->getFeatureOptions();

			$this->processBlacklist();

			add_filter( $oFO->doPluginPrefix( 'visitor_is_whitelisted' ), array( $this, 'fGetIsVisitorWhitelisted' ), 1000 );

			if ( $oFO->getIsAutoBlackListFeatureEnabled() ) {
				// At (29), we come in just before login protect (30) to find an invalid login and black mark it.
				add_filter( 'authenticate', array( $this, 'verifyIfAuthenticationValid' ), 29, 1 );

				// We add text of the current number of transgressions remaining in the Firewall die message
				add_filter( $oFO->doPluginPrefix( 'firewall_die_message' ), array( $this, 'fAugmentFirewallDieMessage' ) );

				// We must allow for black marking of an IP
				add_action( $oFO->doPluginPrefix( 'plugin_shutdown' ), array( $this, 'action_blackMarkIp' ) );
			}
		}

		/**
		 * @param string $sCurrentMessage
		 * @return string
		 */
		public function fAugmentFirewallDieMessage( $sCurrentMessage ) {
			$sCurrentMessage .= sprintf( '<p>%s</p>', $this->getTextOfRemainingTransgressions() );
			return $sCurrentMessage;
		}

		/**
		 * @param WP_User|WP_Error $oUserOrError
		 * @return WP_User|WP_Error
		 */
		public function verifyIfAuthenticationValid( $oUserOrError ) {

			if ( $this->loadWpFunctionsProcessor()->getIsLoginRequest() ) {
				$bUserLoginSuccess = is_object( $oUserOrError ) && ( $oUserOrError instanceof WP_User );
				if ( !$bUserLoginSuccess ) {
					add_filter( $this->getFeatureOptions()->doPluginPrefix( 'ip_black_mark' ), '__return_true' );

					if ( !is_wp_error( $oUserOrError ) ) {
						$oUserOrError = new WP_Error();
					}
					$oUserOrError->add( 'wpsf-autoblacklist', $this->getTextOfRemainingTransgressions() );
				}
			}
			return $oUserOrError;
		}

		/**
		 * @return string
		 */
		private function getTextOfRemainingTransgressions() {
			return sprintf(
				_wpsf__( 'Warning - %s' ),
				sprintf(
					_wpsf__( 'You have %s remaining transgression(s) against this site and then you will be black listed.' ),
					$this->getRemainingTransgressionsForIp()
				)
			);
		}

		protected function getRemainingTransgressionsForIp( $sIp = '' ) {
			/** @var ICWP_WPSF_FeatureHandler_Ips $oFO */
			$oFO = $this->getFeatureOptions();
			if ( empty( $sIp ) ) {
				$sIp = $this->loadDataProcessor()->getVisitorIpAddress();
			}
			return $oFO->getTransgressionLimit() - $this->getCurrentTransgressionsForIp( $sIp );
		}

		protected function getCurrentTransgressionsForIp( $sIp ) {
			if ( empty( $sIp ) ) {
				$sIp = $this->loadDataProcessor()->getVisitorIpAddress();
			}
			$aData = $this->getIpHasTransgressions( $sIp, true );
			return empty( $aData ) ? 0 : $aData[ 'transgressions' ];
		}

		protected function processBlacklist() {
			/** @var ICWP_WPSF_FeatureHandler_Ips $oFO */
			$oFO = $this->getFeatureOptions();

			// the best approach here is to do 2x separate queries.  Not ideal, but the logic behind the automatic black list
			// is comparatively more complex than the simple manual black list, so it doesn't make sense to do 1 big query and
			// then post-process it all. Better to be highly selective with 2x queries.

			$sIp = $this->loadDataProcessor()->getVisitorIpAddress();

			// Manual black list first.
			$bKill = $this->getIsIpOnManualBlackList( $sIp );

			// now try auto black list
			if ( !$bKill && $oFO->getIsAutoBlackListFeatureEnabled() ) {
				$bKill = $this->getIsIpAutoBlackListed( $sIp );
			}

			if ( $bKill ) {
				$sAuditMessage = sprintf( _wpsf__( 'Visitor was found to be on the Manual Black List with IP address "%s" and their connected was killed.' ), $sIp );
				$this->addToAuditEntry( $sAuditMessage, 3, 'black_list_connection_killed' );

				wp_die(
					'<h3>'.sprintf( _wpsf__( 'You have been black listed by the %s plugin.' ),
						'<a href="https://wordpress.org/plugins/wp-simple-firewall/" target="_blank">'.$this->getController()->getHumanName().'</a>'
					).'</h3>'
					.'<br />'.sprintf( _wpsf__( 'You tripped the security plugin defenses a total of %s times making you a suspect.' ), $oFO->getTransgressionLimit() )
					.'<br />'.sprintf( _wpsf__( 'If you believe this to be in error, please contact the site owner.' ) )
				);
			}
		}

		/**
		 * @return boolean
		 */
		public function action_blackMarkIp() {
			$bDoBlackMark = apply_filters( $this->getFeatureOptions()->doPluginPrefix( 'ip_black_mark' ), false );
			if ( $bDoBlackMark ) {
				$this->blackMarkIp( $this->loadDataProcessor()->getVisitorIpAddress() );
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
					_wpsf__( 'Auto Black List transgression counter was incremented from "%s" for visitor at IP address "%s".' ),
					$aIpBlackListData[ 'transgressions' ],
					$sIp
				);
				$this->addToAuditEntry( $sAuditMessage, 2, 'transgression_counter_increment' );
			}
			else {
				$this->query_addNewAutoBlackListIp( $sIp );
				$sAuditMessage = sprintf(
					_wpsf__( 'Auto Black List transgression counter was started for visitor at IP address "%s".' ),
					$aIpBlackListData[ 'transgressions' ],
					$sIp
				);
				$this->addToAuditEntry( $sAuditMessage, 2, 'transgression_counter_started' );
			}
		}

		/**
		 * @param boolean $bIsWhitelisted
		 * @return boolean
		 */
		public function fGetIsVisitorWhitelisted( $bIsWhitelisted ) {
			if ( !isset( $this->bVisitorIsWhitelisted ) ) {
				$sIp = $this->loadDataProcessor()->getVisitorIpAddress();
				$this->bVisitorIsWhitelisted = $this->getIsIpOnWhiteList( $sIp );
			}
			return ( $bIsWhitelisted || $this->bVisitorIsWhitelisted ); //so we still support the legacy lists
		}

		/**
		 * @param string $sIp
		 * @param bool $bReturnListData
		 * @return bool|array
		 */
		public function getIsIpOnWhiteList( $sIp, $bReturnListData = false ) {

			$aIpData = $this->getIpListData( $sIp, self::LIST_MANUAL_WHITE );
			$bOnList = count( $aIpData ) > 0;

			return ( ( $bOnList && $bReturnListData ) ? $aIpData : $bOnList );
		}

		/**
		 * @param string $sIp
		 * @param bool $bReturnListData
		 * @return bool|array
		 */
		public function getIsIpOnBlackLists( $sIp, $bReturnListData = false ) {

			$aIpData = $this->getIpListData( $sIp, array( self::LIST_AUTO_BLACK, self::LIST_MANUAL_BLACK ) );
			$bOnList = count( $aIpData ) > 0;

			return ( ( $bOnList && $bReturnListData ) ? $aIpData : $bOnList );
		}

		/**
		 * @param string $sIp
		 * @param bool $bReturnListData
		 * @return bool|array
		 */
		public function getIsIpOnManualBlackList( $sIp, $bReturnListData = false ) {

			$aIpData = $this->getIpListData( $sIp, self::LIST_MANUAL_BLACK );
			$bOnList = count( $aIpData ) > 0;

			return ( ( $bOnList && $bReturnListData ) ? $aIpData : $bOnList );
		}

		/**
		 * The auto black list isn't a simple lookup, but rather has an auto expiration and a transgression count
		 *
		 * @param string $sIp
		 * @param bool $bReturnListData
		 * @return bool|array - will return the associative array of the single row data
		 */
		public function getIsIpAutoBlackListed( $sIp, $bReturnListData = false ) {
			/** @var ICWP_WPSF_FeatureHandler_Ips $oFO */
			$oFO = $this->getFeatureOptions();

			$nSinceTimeToConsider = $this->time() - $oFO->getAutoExpireTime();
			$nTransgressions = $oFO->getTransgressionLimit();

			$aIpData = $this->query_getAutoBlackListDataForIp( $sIp, $nSinceTimeToConsider, $nTransgressions );
			return ( $bReturnListData ? $aIpData : !empty( $aIpData ) );
		}

		/**
		 * The auto black list isn't a simple lookup, but rather has an auto expiration and a transgression count
		 *
		 * @param string $sIp
		 * @param bool $bReturnListData
		 * @return bool|array - will return the associative array of the single row data
		 */
		public function getIpHasTransgressions( $sIp, $bReturnListData = false ) {
			/** @var ICWP_WPSF_FeatureHandler_Ips $oFO */
			$oFO = $this->getFeatureOptions();

			$nSinceTimeToConsider = $this->time() - $oFO->getAutoExpireTime();

			$aIpData = $this->query_getAutoBlackListDataForIp( $sIp, $nSinceTimeToConsider, 0 );
			return ( $bReturnListData ? $aIpData : !empty( $aIpData ) );
		}

		/**
		 * @param string $sIp
		 * @param array $aLists
		 * @return array
		 */
		public function getIpListData( $sIp, $aLists ) {

			$aData = array();

			$aResult = $this->query_getListData( $aLists );
			foreach( $aResult as $aRow ) {
				if ( $this->loadIpProcessor()->checkIp( $sIp, $aRow[ 'ip' ] ) ) {
					$aData[] = $aRow;
				}
			}

			return $aData;
		}

		/**
		 * @param string $sIp
		 * @return bool|int
		 */
		protected function query_addNewAutoBlackListIp( $sIp ) {

			// Ensure we delete any previous old entries as we go.
			$this->query_deleteIpFromList( $sIp, self::LIST_AUTO_BLACK );

			// Now add new entry
			$aNewData = array();
			$aNewData[ 'ip' ]				= $sIp;
			$aNewData[ 'label' ]			= 'auto';
			$aNewData[ 'list' ]				= self::LIST_AUTO_BLACK;
			$aNewData[ 'ip6' ]				= $this->loadDataProcessor()->getIpAddressVersion( $sIp ) == 6;
			$aNewData[ 'transgressions' ]	= 1;
			$aNewData[ 'range' ]			= 0;
			$aNewData[ 'last_access_at' ]	= $this->time();
			$aNewData[ 'created_at' ]		= $this->time();

			$mResult = $this->insertData( $aNewData );
			return $mResult ? $aNewData : $mResult;
		}

		/**
		 * @param array $aCurrentData
		 * @return bool|int
		 */
		protected function query_updateBmCounterForIp( $aCurrentData ) {
			$aUpdated = array(
				'transgressions'	=> $aCurrentData['transgressions'] + 1,
				'last_access_at'	=> $this->time(),
			);
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
		 *
		 * @param string $sIp
		 * @param int $nSince
		 * @param int $nTransgressionLimit
		 * @return array
		 */
		protected function query_getAutoBlackListDataForIp( $sIp, $nSince = 0, $nTransgressionLimit = 0) {

			$sQuery = "
				SELECT *
				FROM `%s`
				WHERE
					`ip`					= '%s'
					AND `list`				= '%s'
					AND `transgressions`	>= '%s'
					AND `last_access_at`	>= %s
					AND `deleted_at`		= '0'
			";

			$sQuery = sprintf( $sQuery,
				$this->getTableName(),
				$sIp,
				self::LIST_AUTO_BLACK,
				$nTransgressionLimit,
				$nSince
			);
			$mResult = $this->selectCustom( $sQuery );
			return ( is_array( $mResult ) && isset( $mResult[0] ) ) ? $mResult[0] : array();
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
					AND `deleted_at`	= '0'
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
			$sSqlTables = "CREATE TABLE IF NOT EXISTS `%s` (
				`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
				`ip` varchar(40) NOT NULL DEFAULT '',
				`label` varchar(255) NOT NULL DEFAULT '',
				`list` varchar(4) NOT NULL DEFAULT '',
				`ip6` TINYINT(1) NOT NULL DEFAULT 0,
				`range` TINYINT(1) NOT NULL DEFAULT 0,
				`transgressions` TINYINT(2) UNSIGNED NOT NULL DEFAULT '0',
				`last_access_at` INT(15) UNSIGNED NOT NULL DEFAULT '0',
				`created_at` INT(15) UNSIGNED NOT NULL DEFAULT '0',
				`deleted_at` INT(15) UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
			return sprintf( $sSqlTables, $this->getTableName() );
		}

		/**
		 * @return array
		 */
		protected function getTableColumnsByDefinition() {
			return $this->getOption( 'ip_list_table_columns' );
		}

		/**
		 * This is hooked into a cron in the base class and overrides the parent method.
		 * It'll delete everything older than 24hrs.
		 */
		public function cleanupDatabase() {
			if ( !$this->getTableExists() ) {
				return;
			}

			/** @var ICWP_WPSF_FeatureHandler_Ips $oFO */
			$oFO = $this->getFeatureOptions();
			$nSinceTimeToConsider = $this->time() - $oFO->getAutoExpireTime();

			$nTimeStamp = $this->time() - $nSinceTimeToConsider;
			$this->deleteAllRowsOlderThan( $nTimeStamp );
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
			";
			$sQuery = sprintf( $sQuery,
				$this->getTableName(),
				esc_sql( $nTimeStamp )
			);
			return $this->loadDbProcessor()->doSql( $sQuery );
		}
	}

endif;

if ( !class_exists( 'ICWP_WPSF_Processor_Ips', false ) ):
	class ICWP_WPSF_Processor_Ips extends ICWP_WPSF_Processor_Ips_V1 { }
endif;