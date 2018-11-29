<?php

if ( class_exists( 'ICWP_WPSF_Processor_AuditTrail', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/basedb.php' );

class ICWP_WPSF_Processor_AuditTrail extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @var ICWP_WPSF_AuditTrail_Auditor_Base
	 */
	protected $oAuditor;

	/**
	 * @param ICWP_WPSF_FeatureHandler_AuditTrail $oModCon
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_AuditTrail $oModCon ) {
		parent::__construct( $oModCon, $oModCon->getDef( 'audit_trail_table_name' ) );
	}

	/**
	 * @return ICWP_WPSF_AuditTrail_Auditor_Base
	 */
	public function getBaseAuditor() {
		if ( !isset( $this->oAuditor ) ) {
			require_once( dirname( __FILE__ ).'/audit_trail_auditor_base.php' );
			$this->oAuditor = new ICWP_WPSF_AuditTrail_Auditor_Base();
		}
		return $this->oAuditor;
	}

	public function onModuleShutdown() {
		parent::onModuleShutdown();
		if ( !$this->getMod()->isPluginDeleting() ) {
			$this->commitAuditTrial();
		}
	}

	public function cleanupDatabase() {
		parent::cleanupDatabase(); // Deletes based on time.
		$this->trimTable();
	}

	/**
	 * ABstract this and move it into base DB class
	 */
	protected function trimTable() {
		/** @var ICWP_WPSF_FeatureHandler_AuditTrail $oFO */
		$oFO = $this->getMod();
		try {
			$this->getDbHandler()
				 ->getQueryDeleter()
				 ->deleteExcess( $oFO->getMaxEntries() );
		}
		catch ( Exception $oE ) {
		}
	}

	/**
	 */
	public function run() {
		if ( !$this->isReadyToRun() ) {
			return;
		}
		/** @var ICWP_WPSF_FeatureHandler_AuditTrail $oFO */
		$oFO = $this->getMod();

		if ( $oFO->isAuditUsers() ) {
			require_once( dirname( __FILE__ ).'/audit_trail_users.php' );
			$oUsers = new ICWP_WPSF_Processor_AuditTrail_Users();
			$oUsers->run();
		}

		if ( $oFO->isAuditPlugins() ) {
			require_once( dirname( __FILE__ ).'/audit_trail_plugins.php' );
			$oPlugins = new ICWP_WPSF_Processor_AuditTrail_Plugins();
			$oPlugins->run();
		}

		if ( $oFO->isAuditThemes() ) {
			require_once( dirname( __FILE__ ).'/audit_trail_themes.php' );
			$oThemes = new ICWP_WPSF_Processor_AuditTrail_Themes();
			$oThemes->run();
		}

		if ( $oFO->isAuditWp() ) {
			require_once( dirname( __FILE__ ).'/audit_trail_wordpress.php' );
			$oWp = new ICWP_WPSF_Processor_AuditTrail_Wordpress();
			$oWp->run();
		}

		if ( $oFO->isAuditPosts() ) {
			require_once( dirname( __FILE__ ).'/audit_trail_posts.php' );
			$oPosts = new ICWP_WPSF_Processor_AuditTrail_Posts();
			$oPosts->run();
		}

		if ( $oFO->isAuditEmails() ) {
			require_once( dirname( __FILE__ ).'/audit_trail_emails.php' );
			$oEmails = new ICWP_WPSF_Processor_AuditTrail_Emails();
			$oEmails->run();
		}

		if ( $oFO->isAuditShield() ) {
			require_once( dirname( __FILE__ ).'/audit_trail_wpsf.php' );
			$oWpsf = new ICWP_WPSF_Processor_AuditTrail_Wpsf();
			$oWpsf->run();
		}
	}

	/**
	 * @param string $sContext
	 * @return array|bool
	 */
	public function countAuditEntriesForContext( $sContext = 'all' ) {
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail\Select $oCounter */
		$oCounter = $this->getDbHandler()->getQuerySelector();
		if ( $sContext != 'all' ) {
			$oCounter->filterByContext( $sContext );
		}
		return $oCounter->count();
	}

	/**
	 * @CENTRAL
	 * @param string $sContext
	 * @param string $sOrderBy
	 * @param string $sOrder
	 * @param int    $nPage
	 * @param int    $nLimit
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail\EntryVO[]
	 */
	public function getAuditEntriesForContext( $sContext = 'all', $sOrderBy = 'created_at', $sOrder = 'DESC', $nPage = 1, $nLimit = 50 ) {
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail\Select $oSelect */
		$oSelect = $this->getDbHandler()
						->getQuerySelector()
						->setResultsAsVo( true )
						->setOrderBy( $sOrderBy, $sOrder )
						->setLimit( $nLimit )
						->setPage( $nPage );
		if ( $sContext != 'all' ) {
			$oSelect->filterByContext( $sContext );
		}

		return $oSelect->query();
	}

	/**
	 */
	protected function commitAuditTrial() {
		$aEntries = $this->getAuditor()->getAudits();
		if ( !empty( $aEntries ) ) {
			$sReqId = $this->getController()->getShortRequestId();
			$oDbh = $this->getDbHandler();
			/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail\Insert $oInsert */
			$oInsert = $oDbh->getQueryInserter();
			foreach ( $aEntries as $oE ) {
				$oE->rid = $sReqId;
				$oInsert->insert( $oE );
			}
		}
	}

	/**
	 * @return string
	 */
	protected function getCreateTableSql() {
		return "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			rid varchar(10) NOT NULL DEFAULT '' COMMENT 'Request ID',
			ip varchar(40) NOT NULL DEFAULT 0 COMMENT 'Visitor IP Address',
			wp_username varchar(255) NOT NULL DEFAULT 'none' COMMENT 'WP User',
			context varchar(32) NOT NULL DEFAULT 'none' COMMENT 'Audit Context',
			event varchar(50) NOT NULL DEFAULT 'none' COMMENT 'Specific Audit Event',
			category int(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Severity',
			message text COMMENT 'Audit Event Description',
			data text COMMENT 'Audit Event Data',
			immutable tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'May Be Deleted',
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id)
		) %s;";
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		$aDef = $this->getMod()->getDef( 'audit_trail_table_columns' );
		return ( is_array( $aDef ) ? $aDef : array() );
	}

	/**
	 * override and do not delete
	 */
	public function deleteTable() {
	}

	/**
	 * @return int|null
	 */
	protected function getAutoExpirePeriod() {
		/** @var ICWP_WPSF_FeatureHandler_Traffic $oFO */
		$oFO = $this->getMod();
		return $oFO->getAutoCleanDays()*DAY_IN_SECONDS;
	}

	/**
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail\Handler
	 */
	protected function createDbHandler() {
		return new \FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail\Handler();
	}

	/**
	 * @deprecated
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail\Delete
	 */
	public function getQueryDeleter() {
		return parent::getQueryDeleter();
	}

	/**
	 * @deprecated
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail\Insert
	 */
	public function getQueryInserter() {
		return parent::getQueryInserter();
	}

	/**
	 * @deprecated
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail\Select
	 */
	public function getQuerySelector() {
		return parent::getQuerySelector();
	}
}