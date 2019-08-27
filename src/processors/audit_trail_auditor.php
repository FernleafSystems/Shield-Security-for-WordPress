<?php

use FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Options;

class ICWP_WPSF_Processor_AuditTrail_Auditor extends ICWP_WPSF_Processor_BaseDb {

	/**
	 * @var bool
	 */
	private $bAudit = false;

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
}