<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Ips\Options;
use FernleafSystems\Wordpress\Services\Services;

class Handler extends Base\Handler {

	public function autoCleanDb() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		/** @var Options $oOpts */
		$oOpts = $oMod->getOptions();
		/** @var Delete $oDel */
		$oDel = $this->getQueryDeleter();
		$oDel->filterByBlacklist()
			 ->filterByLastAccessBefore( Services::Request()->ts() - $oOpts->getAutoExpireTime() )
			 ->query();
	}

	/**
	 * @param int $nTimeStamp
	 * @return bool
	 */
	public function deleteRowsOlderThan( $nTimeStamp ) {
		return $this->getQueryDeleter()
					->addWhereOlderThan( $nTimeStamp, 'last_access_at' )
					->addWhere( 'list', \ICWP_WPSF_FeatureHandler_Ips::LIST_MANUAL_WHITE, '!=' )
					->query();
	}

	/**
	 * @return string[]
	 */
	protected function getDefaultColumnsDefinition() {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->getDbColumns_IPs();
	}

	/**
	 * @return string
	 */
	protected function getDefaultTableName() {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->getDbTable_IPs();
	}

	/**
	 * @return string[]
	 */
	protected function getColumnsAsArray() {
		return [
			'ip'             => "varchar(60) NOT NULL DEFAULT '' COMMENT 'Human readable IP address or range'",
			'label'          => "varchar(255) NOT NULL DEFAULT ''",
			'transgressions' => "smallint(1) UNSIGNED NOT NULL DEFAULT 0",
			'list'           => "varchar(4) NOT NULL DEFAULT ''",
			'ip6'            => "tinyint(1) UNSIGNED NOT NULL DEFAULT 0",
			'is_range'       => "tinyint(1) UNSIGNED NOT NULL DEFAULT 0",
			'last_access_at' => "int(15) UNSIGNED NOT NULL DEFAULT 0",
			'blocked_at'     => "int(15) UNSIGNED NOT NULL DEFAULT 0",
		];
	}
}