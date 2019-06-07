<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Options;
use FernleafSystems\Wordpress\Services\Services;

class Handler extends Base\Handler {

	/**
	 * @param $aEvents - array of events: key event slug, value created_at timestamp
	 */
	public function commitEvents( $aEvents ) {
		foreach ( $aEvents as $sEvent => $nTs ) {
			/** @var EntryVO $oEvt */
			$oEvt = $this->getVo();
			$oEvt->event = $sEvent;
			$oEvt->count = 1;
			$oEvt->created_at = empty( $nTs ) ? Services::Request()->ts() : $nTs;
			/** @var Insert $oQI */
			$oQI = $this->getQueryInserter();
			$oQI->insert( $oEvt );
		}
	}

	/**
	 * @return string[]
	 */
	protected function getDefaultColumnsDefinition() {
		/** @var Options $oOpts */
		$oOpts = $this->getMod()->getOptions();
		return $oOpts->getDbColumns_Events();
	}

	/**
	 * @return string
	 */
	protected function getDefaultTableName() {
		/** @var Options $oOpts */
		$oOpts = $this->getMod()->getOptions();
		return $oOpts->getDbTable_Events();
	}

	/**
	 * @return string
	 */
	protected function getDefaultCreateTableSql() {
		return "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			event varchar(50) NOT NULL DEFAULT 'none' COMMENT 'Event ID',
			count int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total',
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id)
		) %s;";
	}
}