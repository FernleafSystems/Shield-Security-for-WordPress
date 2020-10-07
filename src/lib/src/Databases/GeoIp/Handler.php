<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\GeoIp;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;

class Handler extends Base\EnumeratedColumnsHandler {

	public function autoCleanDb() {
		$this->tableCleanExpired( $this->getOptions()->getDef( 'db_autoexpire_geoip' ) );
	}

	/**
	 * @return string[]
	 */
	public function getColumnsAsArray() :array {
		return $this->getOptions()->getDef( 'geoip_table_columns' );
	}

	/**
	 * @return string
	 */
	protected function getDefaultTableName() :string {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->getDbTable_GeoIp();
	}
}