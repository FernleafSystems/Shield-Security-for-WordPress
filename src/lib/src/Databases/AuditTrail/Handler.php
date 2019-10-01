<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Options;
use FernleafSystems\Wordpress\Services\Services;

class Handler extends Base\Handler {

	public function autoCleanDb() {
		/** @var \ICWP_WPSF_FeatureHandler_AuditTrail $oMod */
		$oMod = $this->getMod();
		/** @var Options $oOpts */
		$oOpts = $oMod->getOptions();
		$this->cleanDb( $oOpts->getAutoCleanDays() );
		$this->trimDb( $oOpts->getMaxEntries() );
	}

	/**
	 * @param $aEvents - array of events: key event slug, value created_at timestamp
	 */
	public function commitAudits( $aEvents ) {
		foreach ( $aEvents as $oEntry ) {
			$this->commitAudit( $oEntry );
		}
	}

	/**
	 * @param EntryVO $oEntry
	 */
	public function commitAudit( $oEntry ) {
		$oWp = Services::WpGeneral();
		$oWpUsers = Services::WpUsers();

		$oEntry->rid = $this->getCon()->getShortRequestId();
		if ( empty( $oEntry->message ) ) {
			$oEntry->message = '';
		}
		if ( empty( $oEntry->wp_username ) ) {
			if ( $oWpUsers->isUserLoggedIn() ) {
				$sUser = $oWpUsers->getCurrentWpUsername();
			}
			else if ( $oWp->isCron() ) {
				$sUser = 'WP Cron';
			}
			else if ( $oWp->isWpCli() ) {
				$sUser = 'WP CLI';
			}
			else {
				$sUser = '-';
			}
			$oEntry->wp_username = $sUser;
		}
		/** @var Insert $oQI */
		$oQI = $this->getQueryInserter();
		$oQI->insert( $oEntry );
	}

	/**
	 * @return array
	 */
	protected function getDefaultColumnsDefinition() {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->getDbColumns_AuditTrail();
	}

	/**
	 * @return string
	 */
	protected function getDefaultTableName() {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->getDbTable_AuditTrail();
	}

	/**
	 * @return string
	 */
	protected function getDefaultCreateTableSql() {
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
}