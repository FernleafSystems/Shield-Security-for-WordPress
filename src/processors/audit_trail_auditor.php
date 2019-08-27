<?php

use FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Options;

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
	 */
	public function run() {
		if ( !$this->isReadyToRun() ) {
			return;
		}
		$this->bAudit = true;

		/** @var \ICWP_WPSF_FeatureHandler_AuditTrail $oMod */
		$oMod = $this->getMod();
		/** @var Options $oOpts */
		$oOpts = $oMod->getOptions();

		if ( $oOpts->isAuditUsers() ) {
			( new Auditors\Users() )
				->setMod( $oMod )
				->run();
		}
		if ( $oOpts->isAuditPlugins() ) {
			( new Auditors\Plugins() )
				->setMod( $oMod )
				->run();
		}
		if ( $oOpts->isAuditThemes() ) {
			( new Auditors\Themes() )
				->setMod( $oMod )
				->run();
		}
		if ( $oOpts->isAuditWp() ) {
			( new Auditors\Wordpress() )
				->setMod( $oMod )
				->run();
		}
		if ( $oOpts->isAuditPosts() ) {
			( new Auditors\Posts() )
				->setMod( $oMod )
				->run();
		}
		if ( $oOpts->isAuditEmails() ) {
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

	/**
	 * @param string $sContext
	 * @return array|bool
	 */
	public function countAuditEntriesForContext( $sContext = 'all' ) {
		/** @var ICWP_WPSF_FeatureHandler_AuditTrail $oFO */
		$oFO = $this->getMod();
		/** @var AuditTrail\Select $oCounter */
		$oCounter = $oFO->getDbHandler()->getQuerySelector();
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
		/** @var \ICWP_WPSF_FeatureHandler_AuditTrail $oFO */
		$oFO = $this->getMod();
		/** @var AuditTrail\Select $oSelect */
		$oSelect = $oFO->getDbHandler()
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
	 * @return string
	 */
	protected function getCreateTableSql() {
		return "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			rid varchar(10) NOT NULL DEFAULT '' COMMENT 'Request ID',
			ip varchar(40) NOT NULL DEFAULT 0 COMMENT 'Visitor IP Address',
			wp_username varchar(255) NOT NULL DEFAULT '-' COMMENT 'WP User',
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
	 * @return int|null
	 */
	protected function getAutoExpirePeriod() {
		/** @var \ICWP_WPSF_FeatureHandler_AuditTrail $oFO */
		$oFO = $this->getMod();
		return $oFO->getAutoCleanDays()*DAY_IN_SECONDS;
	}
}