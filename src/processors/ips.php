<?php

if ( !class_exists( 'ICWP_WPSF_Processor_Ips_V1', false ) ):

	require_once( dirname(__FILE__).ICWP_DS.'base.php' );

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

			add_filter( $oFO->doPluginPrefix( 'visitor_is_whitelisted' ), array( $this, 'fGetIsVisitorWhitelisted' ), 1000 );

			$aIpBlackListData = $this->getIsIpOnBlackLists( $this->loadDataProcessor()->getVisitorIpAddress(), true );
			if ( is_array( $aIpBlackListData ) ) {
				$bKill = false;
				foreach( $aIpBlackListData as $aIpData ) {

					if ( $aIpData['list'] == self::LIST_MANUAL_BLACK ) {
						$bKill = true;
						break;
					}
					if ( $aIpData['list'] == self::LIST_AUTO_BLACK ) {

						if ( $aIpData['bm_counter'] >= 3 ) { // TODO: set a configurable option.
							$bKill = true;
							break;
						}
					}
				}
				if ( $bKill ) {
					wp_die( 'you be on the ip blacklist' );
				}
			}

			add_action( $oFO->doPluginPrefix( 'plugin_shutdown' ), array( $this, 'action_blackMarkIp' ) );
		}

		/**
		 * @param boolean $bIsWhitelisted
		 * @return boolean
		 */
		public function action_blackMarkIp( $bIsWhitelisted ) {
			$bDoBlackMark = apply_filters( $this->getFeatureOptions()->doPluginPrefix( 'ip_black_mark' ), false );
			if ( $bDoBlackMark ) {
				$sIp = $this->loadDataProcessor()->getVisitorIpAddress();
				$this->bVisitorIsWhitelisted = $this->getIsIpOnWhiteList( $sIp );
			}
			return ( $bIsWhitelisted || $this->bVisitorIsWhitelisted ); //so we still support the legacy lists
		}

		protected function blackMarkIp() {

			$sIp = $this->loadDataProcessor()->getVisitorIpAddress();
			$aIpBlackListData = $this->getIsIpOnAutoBlackList( $sIp, true );
			if ( count( $aIpBlackListData ) > 0 ) {
				$this->query_updateBmCounterForIp( $aIpBlackListData[ 0 ] );
			}
			else {
				$this->query_addNewAutoBlackListIp( $sIp );
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
		 * @param string $sIp
		 * @param bool $bReturnListData
		 * @return bool|array
		 */
		public function getIsIpOnAutoBlackList( $sIp, $bReturnListData = false ) {

			$aIpData = $this->getIpListData( $sIp, self::LIST_AUTO_BLACK );
			$bOnList = count( $aIpData ) > 0;

			return ( ( $bOnList && $bReturnListData ) ? $aIpData : $bOnList );
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

			// Now add new pending entry
			$aNewData = array();
			$aNewData[ 'ip' ]				= $sIp;
			$aNewData[ 'label' ]			= 'auto';
			$aNewData[ 'list' ]				= self::LIST_AUTO_BLACK;
			$aNewData[ 'ip6' ]				= $this->loadDataProcessor()->getIpAddressVersion( $sIp ) == 6;
			$aNewData[ 'bm_counter' ]		= 1;
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
				'bm_counter'		=> $aCurrentData['bm_counter'] + 1,
				'last_access_at'	=> $this->time(),
			);
			return $this->updateRowsWhere( $aUpdated, $aCurrentData );
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
				`bm_counter` TINYINT(2) UNSIGNED NOT NULL DEFAULT '0',
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
			$nTimeStamp = $this->time() - ( DAY_IN_SECONDS * $this->nDaysToKeepLog );
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
					`last_access_at`	< '%s'
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