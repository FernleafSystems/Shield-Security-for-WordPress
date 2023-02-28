<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

class Update {

	use ModConsumer;
	use ScanControllerConsumer;

	public function clearIgnored() {
		Services::WpDb()->doSql(
			sprintf( $this->getBaseQuery(),
				implode( ', ', [
					"`ri`.`ignored_at`=0",
					sprintf( "`ri`.`updated_at`=%s", Services::Request()->ts() ),
				] ),
				implode( ' AND ', [
					sprintf( "`scans`.`scan`='%s'", $this->getScanController()->getSlug() )
				] )
			)
		);
	}

	private function getBaseQuery() :string {
		$mod = $this->mod();
		return sprintf( "UPDATE `%s` as ri
						INNER JOIN `%s` as `sr`
							ON `ri`.id = `sr`.resultitem_ref
						INNER JOIN `%s` as `scans`
							ON `scans`.id = `sr`.scan_ref
						SET %%s
						WHERE %%s",
			$mod->getDbH_ResultItems()->getTableSchema()->table,
			$mod->getDbH_ScanResults()->getTableSchema()->table,
			$mod->getDbH_Scans()->getTableSchema()->table
		);
	}
}