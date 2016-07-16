<?php

if ( !class_exists( 'ICWP_WPSF_Processor_Statistics', false ) ):

	require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'basedb.php' );

	class ICWP_WPSF_Processor_Statistics extends ICWP_WPSF_BaseDbProcessor {
		/**
		 * @param ICWP_WPSF_FeatureHandler_Statistics $oFeatureOptions
		 */
		public function __construct( ICWP_WPSF_FeatureHandler_Statistics $oFeatureOptions ) {
			parent::__construct( $oFeatureOptions, $oFeatureOptions->getStatisticsTableName() );
		}

		public function run() {
			//temporary:
			add_filter( $this->getFeatureOptions()->doPluginPrefix( 'collect_stats' ), array( $this, 'audit_CollectOldStats' ) );
			add_action( 'wp_dashboard_setup', array( $this, 'initDashboardWidget' ) );
		}

		public function initDashboardWidget() {
			wp_add_dashboard_widget(
				$this->getFeatureOptions()->doPluginPrefix( 'example_dashboard_widget' ),
				_wpsf__('Shield Statistics'),
				array( $this, 'displayStatsSummaryWidget' )
			);
		}

		public function displayStatsSummaryWidget() {
			/** @var ICWP_WPSF_FeatureHandler_Statistics $oFO */
			$oFO = $this->getFeatureOptions();

			$aAllStats = $this->query_getAllStatData( array( 'stat_key', 'tally' ) );
			$nTotalCommentSpamBlocked = 0;
			$nTotalLoginBlocked = 0;
			$nTotalFirewallBlocked = 0;
			$nTotalConnectionKilled = 0;
			$nTotalTransgressions = 0;

			$aSpamCommentKeys = array(
				'spam.gasp.checkbox',
				'spam.gasp.token',
				'spam.gasp.honeypot',
				'spam.recaptcha.empty',
				'spam.recaptcha.failed',
				'spam.human.comment_content',
				'spam.human.url',
				'spam.human.author_name',
				'spam.human.author_email',
				'spam.human.ip_address',
				'spam.human.user_agent'
			);
			$aLoginFailKeys = array(
				'login.cooldown.fail',
				'login.recaptcha.fail',
				'login.gasp.checkbox.fail',
				'login.gasp.honeypot.fail',
			);
			foreach( $aAllStats as $aStat ) {
				$sStatKey = $aStat[ 'stat_key' ];
				$nTally = $aStat[ 'tally' ];
				if ( in_array( $sStatKey, $aSpamCommentKeys ) ) {
					$nTotalCommentSpamBlocked = $nTotalCommentSpamBlocked + $nTally;
				}
				else if ( strpos( $sStatKey, 'firewall.blocked.' ) ) {
					$nTotalFirewallBlocked = $nTotalFirewallBlocked + $nTally;
				}
				else if ( in_array( $sStatKey, $aLoginFailKeys ) ) {
					$nTotalLoginBlocked = $nTotalLoginBlocked + $nTally;
				}
				else if ( $sStatKey == 'ip.connection.killed' ) {
					$nTotalConnectionKilled = $nTally;
				}
				else if ( $sStatKey == 'ip.transgression.incremented' ) {
					$nTotalTransgressions = $nTally;
				}
			}

			$aKeyStats = array(
				'comments' => array( _wpsf__( 'Comment Blocks' ), $nTotalCommentSpamBlocked ),
				'firewall' => array( _wpsf__( 'Firewall Blocks' ), $nTotalFirewallBlocked ),
				'login' => array( _wpsf__( 'Login Blocks' ), $nTotalLoginBlocked ),
				'ip_killed' => array( _wpsf__( 'IP Auto Black-Listed' ), $nTotalConnectionKilled ),
				'ip_transgressions' => array( _wpsf__( 'Total Transgressions' ), $nTotalTransgressions ),
			);

			$aDisplayData = array(
				'aAllStats' => $aAllStats,
				'aKeyStats' => $aKeyStats,
			);
			echo $oFO->renderTemplate( 'widgets/widget_dashboard_statistics.php', $aDisplayData );
		}

		/**
		 * Filter for importing the old statistics.
		 *
		 * @param $aStats
		 * @return array
		 */
		public function audit_CollectOldStats( $aStats ) {
			$this->loadStatsProcessor();
			$aExisting = ICWP_Stats_WPSF::GetStatsData();
			if ( !empty( $aExisting ) && is_array( $aExisting ) ) {
				foreach ( $aExisting as $sStatKey => $nTally ) {
					if ( is_array( $nTally ) ) {
						foreach( $nTally as $sSubKey => $nNewTally ) {
							$sNewKey = $sSubKey.':'.$sStatKey;
							$aExisting[ $sNewKey ] = $nNewTally;
						}
						unset( $aExisting[ $sStatKey ] );
					}
				}
				$aStats[] = $aExisting;
				ICWP_Stats_WPSF::ClearStats();
			}
			return $aStats;
		}

		/**
		 * @param string $sStatKey
		 * @param int $nTally
		 * @param string $sParentStat
		 * @return bool|int
		 */
		protected function query_addNewStatEntry( $sStatKey, $nTally, $sParentStat = '' ) {
			if ( empty( $sStatKey ) || empty( $nTally ) || !is_numeric( $nTally ) || $nTally < 0 ) {
				return false;
			}

			// Now add new entry
			$aNewData = array();
			$aNewData[ 'stat_key' ]			= $sStatKey;
			$aNewData[ 'parent_stat_key' ]	= $sParentStat;
			$aNewData[ 'tally' ]			= $nTally;
			$aNewData[ 'modified_at' ]		= $this->time();
			$aNewData[ 'created_at' ]		= $this->time();

			$mResult = $this->insertData( $aNewData );
			return $mResult ? $aNewData : $mResult;
		}

		/**
		 * @param string $sStatKey
		 * @param string $sParentStat
		 * @param int $nNewTally
		 * @return bool|int
		 */
		protected function query_updateTallyForStat( $sStatKey, $nNewTally, $sParentStat = '' ) {
			if ( empty( $sStatKey ) || empty( $nNewTally ) || !is_numeric( $nNewTally ) || $nNewTally < 0 ) {
				return false;
			}

			$aCurrentData = array(
				'stat_key'			=> $sStatKey,
				'parent_stat_key'	=> $sParentStat,
			);
			$aUpdated = array(
				'tally'			=> $nNewTally,
				'modified_at'	=> $this->time()
			);
			return $this->updateRowsWhere( $aUpdated, $aCurrentData );
		}

		/**
		 * @param string $sStatKey
		 * @param string $sParentStatKey
		 * @return array|bool|mixed
		 */
		protected function query_getStatData( $sStatKey, $sParentStatKey = '' ) {

			$sStatKey = esc_sql( $sStatKey ); //just in-case someones tries to get all funky up in it
			if ( empty( $sStatKey ) ) {
				return false;
			}

			// Try to get the database entry that corresponds to this set of data. If we get nothing, fail.
			$sQuery = "
				SELECT *
					FROM `%s`
				WHERE
					`stat_key`			= '%s'
					AND `parent_stat_key`	= '%s'
					AND `deleted_at`	= '0'
			";
			$sQuery = sprintf( $sQuery,
				$this->getTableName(),
				$sStatKey,
				$sParentStatKey
			);
			$mResult = $this->selectCustom( $sQuery );
			return ( is_array( $mResult ) && isset( $mResult[0] ) ) ? $mResult[0] : array();
		}

		/**
		 * @param array $aColumns Leave empty to select all (*) columns
		 * @return array
		 */
		protected function query_getAllStatData( $aColumns = array() ) {

			// Try to get the database entry that corresponds to this set of data. If we get nothing, fail.
			$sQuery = "
				SELECT %s
					FROM `%s`
				WHERE
					`deleted_at`	= 0
			";
			$aColumns = $this->validateColumnsParameter( $aColumns );
			$sSelection = empty( $aColumns ) ? '*' : implode( ',', $aColumns );

			$sQuery = sprintf( $sQuery,
				$sSelection,
				$this->getTableName()
			);
			$mResult = $this->selectCustom( $sQuery );
			return ( is_array( $mResult ) && isset( $mResult[0] ) ) ? $mResult : array();
		}

		public function action_doFeatureProcessorShutdown () {
			parent::action_doFeatureProcessorShutdown();
			if ( ! $this->getFeatureOptions()->getIsPluginDeleting() ) {
				$this->commit();
			}
		}

		/**
		 * @return string
		 */
		protected function getTableColumnsByDefinition() {
			return $this->getFeatureOptions()->getDefinition( 'statistics_table_columns' );
		}

		/**
		 * @return string
		 */
		protected function getCreateTableSql() {
			$sSqlTables = "CREATE TABLE %s (
				id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
				stat_key varchar(100) NOT NULL DEFAULT 0,
				parent_stat_key varchar(100) NOT NULL DEFAULT '',
				tally int(11) UNSIGNED NOT NULL DEFAULT 0,
				created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
				modified_at int(15) UNSIGNED NOT NULL DEFAULT 0,
				deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY  (id)
			) %s;";
			return sprintf( $sSqlTables, $this->getTableName(), $this->loadDbProcessor()->getCharCollate() );
		}

		/**
		 */
		protected function commit() {
			$aEntries = apply_filters( $this->getFeatureOptions()->doPluginPrefix( 'collect_stats' ), array() );
			if ( empty( $aEntries ) || !is_array( $aEntries ) ) {
				return;
			}
			foreach( $aEntries as $aCollection ) {
				foreach( $aCollection as $sStatKey => $nTally ) {

					$sParentStatKey = '-';
					if ( strpos( $sStatKey, ':' ) > 0 ) {
						list( $sStatKey, $sParentStatKey ) = explode( ':', $sStatKey, 2 );
					}

					$aCurrentData = $this->query_getStatData( $sStatKey, $sParentStatKey );
					if ( empty( $aCurrentData ) ) {
						$this->query_addNewStatEntry( $sStatKey, $nTally, $sParentStatKey );
					}
					else {
						$this->query_updateTallyForStat( $sStatKey, $aCurrentData[ 'tally' ] + $nTally, $sParentStatKey );
					}
				}
			}
		}
	}

endif;