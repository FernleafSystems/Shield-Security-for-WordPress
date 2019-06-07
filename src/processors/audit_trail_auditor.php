<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;

class ICWP_WPSF_Processor_AuditTrail_Auditor extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @var bool
	 */
	private $bAudit = false;

	/**
	 * @param ICWP_WPSF_FeatureHandler_AuditTrail $oModCon
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_AuditTrail $oModCon ) {
		parent::__construct( $oModCon, $oModCon->getDef( 'audit_trail_table_name' ) );
	}

	/**
	 * Resets the object values to be re-used anew
	 */
	public function init() {
		parent::init();
		add_action( $this->getCon()->prefix( 'add_new_audit_entry' ), [ $this, 'addAuditTrialEntry' ] );
	}

	/**
	 */
	public function run() {
		if ( !$this->isReadyToRun() ) {
			return;
		}
		$this->bAudit = true;

		/** @var ICWP_WPSF_FeatureHandler_AuditTrail $oMod */
		$oMod = $this->getMod();

		if ( $oMod->isAuditUsers() ) {
			( new Auditors\Users() )
				->setMod( $oMod )
				->run();
		}
		if ( $oMod->isAuditPlugins() ) {
			( new Auditors\Plugins() )
				->setMod( $oMod )
				->run();
		}
		if ( $oMod->isAuditThemes() ) {
			( new Auditors\Themes() )
				->setMod( $oMod )
				->run();
		}
		if ( $oMod->isAuditWp() ) {
			( new Auditors\Wordpress() )
				->setMod( $oMod )
				->run();
		}
		if ( $oMod->isAuditPosts() ) {
			( new Auditors\Posts() )
				->setMod( $oMod )
				->run();
		}
		if ( $oMod->isAuditEmails() ) {
			( new Auditors\Emails() )
				->setMod( $oMod )
				->run();
		}
	}

	public function onModuleShutdown() {
		parent::onModuleShutdown();
		if ( $this->bAudit && !$this->getCon()->isPluginDeleting() ) {
			/** @var \ICWP_WPSF_FeatureHandler_Events $oMod */
			$oMod = $this->getMod();
			/** @var AuditTrail\Handler $oDbh */
			$oDbh = $oMod->getDbHandler();
			$oDbh->commitAudits( $oMod->getRegisteredAuditLogs( true ) );
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
		catch ( \Exception $oE ) {
		}
	}

	/**
	 * @param string $sContext
	 * @return array|bool
	 */
	public function countAuditEntriesForContext( $sContext = 'all' ) {
		/** @var AuditTrail\Select $oCounter */
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
	 * @return AuditTrail\EntryVO[]
	 */
	public function getAuditEntriesForContext( $sContext = 'all', $sOrderBy = 'created_at', $sOrder = 'DESC', $nPage = 1, $nLimit = 50 ) {
		/** @var AuditTrail\Select $oSelect */
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
	 * @param AuditTrail\EntryVO $oEntryVo
	 */
	public function addAuditTrialEntry( $oEntryVo ) {
		$oCon = $this->getCon();
		if ( !$oCon->isPluginDeleting() && $oEntryVo instanceof AuditTrail\EntryVO ) {
			$oEntryVo->rid = $oCon->getShortRequestId();
			/** @var AuditTrail\Insert $oInsQ */
			$oInsQ = $this->getDbHandler()->getQueryInserter();
			$oInsQ->insert( $oEntryVo );
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
			meta text COMMENT 'Audit Event Data',
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
		return ( is_array( $aDef ) ? $aDef : [] );
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
	 * @return AuditTrail\Handler
	 */
	protected function createDbHandler() {
		return new AuditTrail\Handler();
	}
}