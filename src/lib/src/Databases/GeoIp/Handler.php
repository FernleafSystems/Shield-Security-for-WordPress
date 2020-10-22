<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\GeoIp;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Handler extends Base\Handler {

	public function autoCleanDb() {
		$this->tableCleanExpired( $this->getOptions()->getDef( 'db_autoexpire_geoip' ) );
	}

	public function getCustomColumns() :array {
		return $this->getOptions()->getDef( 'geoip_table_columns' );
	}

	protected function getDefaultTableName() :string {
		return $this->getOptions()->getDef( 'geoip_table_name' );
	}
}