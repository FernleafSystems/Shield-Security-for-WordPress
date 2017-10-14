<?php

if ( class_exists( 'ICWP_WPSF_Processor_Statistics', false ) ) {
	return;
}

require_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'basedb.php' );

class ICWP_WPSF_Processor_Statistics extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @var ICWP_WPSF_Processor_Statistics_Reporting
	 */
	private $oReportingProcessor;

	/**
	 * @param ICWP_WPSF_FeatureHandler_Statistics $oFeatureOptions
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Statistics $oFeatureOptions ) {
		parent::__construct( $oFeatureOptions, $oFeatureOptions->getStatisticsTableName() );
	}

	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_Statistics $oFO */
		$oFO = $this->getFeature();
		if ( $this->readyToRun() ) {
			add_filter( $oFO->prefix( 'dashboard_widget_content' ), array( $this, 'gatherStatsSummaryWidgetContent' ), 10 );
		}

		// Reporting stats run or destroy
		if ( $this->loadDataProcessor()->getPhpVersionIsAtLeast( '5.3.0' ) ) {
			$this->getReportingProcessor()
				 ->run();
		}
		else { // delete the table for any site that had it running previously
			// TODO: Delete this block after a while.
			$oDb = $this->loadDbProcessor();
			$oDb->doDropTable( $oDb->getPrefix().$oFO->getReportingTableName() );
		}
	}

	/**
	 * Override the original collection to then add plugin statistics to the mix
	 * @param $aData
	 * @return array
	 */
	public function tracking_DataCollect( $aData ) {
		$aData = parent::tracking_DataCollect( $aData );
		$aTallys = $this->getAllTallys();
		$aTallyTracking = array();
		foreach ( $aTallys as $aTally ) {
			$sKey = preg_replace( '#[^_a-z]#', '', str_replace( '.', '_', $aTally[ 'stat_key' ] ) );
			if ( strpos( $sKey, '_' ) ) {
				$aTallyTracking[ $sKey ] = (int)$aTally[ 'tally' ];
			}
		}
		$aData[ $this->getFeature()->getFeatureSlug() ][ 'stats' ] = $aTallyTracking;
		return $aData;
	}

	public function gatherStatsSummaryWidgetContent( $aContent ) {
		/** @var ICWP_WPSF_FeatureHandler_Statistics $oFO */
		$oFO = $this->getFeature();

		$aAllStats = $this->getAllTallys();
		$nTotalCommentSpamBlocked = 0;
		$nTotalLoginBlocked = 0;
		$nTotalLoginVerified = 0;
		$nTotalFirewallBlocked = 0;
		$nTotalConnectionKilled = 0;
		$nTotalTransgressions = 0;
		$nTotalUserSessionsStarted = 0;
//			$nTotalFilesReplaced = 0;

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
			'login.googleauthenticator.fail',
			'login.rename.fail',
		);
		$aLoginVerifiedKeys = array(
			'login.googleauthenticator.verified',
			'login.recaptcha.verified',
			'login.twofactor.verified'
		);
		foreach ( $aAllStats as $aStat ) {
			$sStatKey = $aStat[ 'stat_key' ];
			$nTally = $aStat[ 'tally' ];
			if ( in_array( $sStatKey, $aSpamCommentKeys ) ) {
				$nTotalCommentSpamBlocked = $nTotalCommentSpamBlocked + $nTally;
			}
			else if ( strpos( $sStatKey, 'firewall.blocked.' ) !== false ) {
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
			else if ( $sStatKey == 'user.session.start' ) {
				$nTotalUserSessionsStarted = $nTally;
			}
			else if ( $sStatKey == 'file.corechecksum.replaced' ) {
				$nTotalUserSessionsStarted = $nTally;
			}
			else if ( in_array( $sStatKey, $aLoginVerifiedKeys ) ) {
				$nTotalLoginVerified = $nTotalLoginVerified + $nTally;
			}
		}

		$aKeyStats = array(
			'comments'          => array( _wpsf__( 'Comment Blocks' ), $nTotalCommentSpamBlocked ),
			'firewall'          => array( _wpsf__( 'Firewall Blocks' ), $nTotalFirewallBlocked ),
			'login_fail'        => array( _wpsf__( 'Login Blocks' ), $nTotalLoginBlocked ),
			'login_verified'    => array( _wpsf__( 'Login Verified' ), $nTotalLoginVerified ),
			'session_start'     => array( _wpsf__( 'User Sessions' ), $nTotalUserSessionsStarted ),
			//				'file_replaced' => array( _wpsf__( 'Files Replaced' ), $nTotalFilesReplaced ),
			'ip_killed'         => array( _wpsf__( 'IP Auto Black-Listed' ), $nTotalConnectionKilled ),
			'ip_transgressions' => array( _wpsf__( 'Total Transgressions' ), $nTotalTransgressions ),
		);

		$aDisplayData = array(
			'sHeading'  => _wpsf__( 'Shield Statistics' ),
			'aAllStats' => $aAllStats,
			'aKeyStats' => $aKeyStats,
		);

		if ( !is_array( $aContent ) ) {
			$aContent = array();
		}
		$aContent[] = $oFO->renderTemplate( 'snippets/widget_dashboard_statistics.php', $aDisplayData );
		return $aContent;
	}

	/**
	 * @param string $sStatKey
	 * @param int    $nTally
	 * @param string $sParentStat
	 * @return bool|int
	 */
	protected function query_addNewStatEntry( $sStatKey, $nTally, $sParentStat = '' ) {
		if ( empty( $sStatKey ) || empty( $nTally ) || !is_numeric( $nTally ) || $nTally < 0 ) {
			return false;
		}

		// Now add new entry
		$aNewData = array();
		$aNewData[ 'stat_key' ] = $sStatKey;
		$aNewData[ 'parent_stat_key' ] = $sParentStat;
		$aNewData[ 'tally' ] = $nTally;
		$aNewData[ 'modified_at' ] = $this->time();
		$aNewData[ 'created_at' ] = $this->time();

		$mResult = $this->insertData( $aNewData );
		return $mResult ? $aNewData : $mResult;
	}

	/**
	 * @param string $sStatKey
	 * @param string $sParentStat
	 * @param int    $nNewTally
	 * @return bool|int
	 */
	protected function query_updateTallyForStat( $sStatKey, $nNewTally, $sParentStat = '' ) {
		if ( empty( $sStatKey ) || empty( $nNewTally ) || !is_numeric( $nNewTally ) || $nNewTally < 0 ) {
			return false;
		}

		$aCurrentData = array(
			'stat_key'        => $sStatKey,
			'parent_stat_key' => $sParentStat,
		);
		$aUpdated = array(
			'tally'       => $nNewTally,
			'modified_at' => $this->time()
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
					`stat_key`				= '%s'
					AND `parent_stat_key`	= '%s'
					AND `deleted_at`		= 0
			";
		$sQuery = sprintf( $sQuery,
			$this->getTableName(),
			$sStatKey,
			$sParentStatKey
		);
		$mResult = $this->selectCustom( $sQuery );
		return ( is_array( $mResult ) && isset( $mResult[ 0 ] ) ) ? $mResult[ 0 ] : array();
	}

	/**
	 * @return array|bool
	 */
	protected function query_deleteInvalidStatKeys() {
		$sQuery = "
				DELETE FROM `%s`
				WHERE `stat_key` NOT LIKE '%%.%%'
			";
		return $this->selectCustom( sprintf( $sQuery, $this->getTableName() ) );
	}

	/**
	 * @return array
	 */
	protected function getAllTallys() {
		return $this->query_selectAll( array( 'stat_key', 'tally' ) );
	}

	public function action_doFeatureProcessorShutdown() {
		parent::action_doFeatureProcessorShutdown();
		if ( !$this->getFeature()->isPluginDeleting() ) {
			$this->commit();
		}
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		$aDef = $this->getFeature()->getDefinition( 'statistics_table_columns' );
		return ( is_array( $aDef ) ? $aDef : array() );
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
		$aEntries = apply_filters( $this->getFeature()->prefix( 'collect_stats' ), array() );
		if ( empty( $aEntries ) || !is_array( $aEntries ) ) {
			return;
		}
		foreach ( $aEntries as $aCollection ) {
			foreach ( $aCollection as $sStatKey => $nTally ) {

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

	/**
	 * We override this to clean out any strange statistics entries (Human spam words mostly)
	 * @return bool|int
	 */
	public function cleanupDatabase() {
		parent::cleanupDatabase();
		return $this->query_deleteInvalidStatKeys();
	}

	/**
	 * override and do not delete
	 */
	public function deleteTable() {}

	/**
	 * @return ICWP_WPSF_Processor_Statistics_Reporting
	 */
	protected function getReportingProcessor() {
		if ( !isset( $this->oReportingProcessor ) ) {
			require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'statistics_reporting.php' );
			$this->oReportingProcessor = new ICWP_WPSF_Processor_Statistics_Reporting( $this->getFeature() );
		}
		return $this->oReportingProcessor;
	}
}