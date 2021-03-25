<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\GeoIp;

class Handler extends \FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\Handler {

	public function autoCleanDb() {
		$this->tableCleanExpired( (int)$this->getTableSchema()->autoexpire );
	}
}