<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Update {

	use PluginControllerConsumer;
	use ScanControllerConsumer;

	public function clearIgnored() {
		Services::WpDb()->doSql(
			sprintf( $this->getBaseQuery(),
				\implode( ', ', [
					"`ri`.`ignored_at`=0",
					sprintf( "`ri`.`updated_at`=%s", Services::Request()->ts() ),
				] ),
				\implode( ' AND ', [
					sprintf( "`scans`.`scan`='%s'", $this->getScanController()->getSlug() )
				] )
			)
		);
	}

	private function getBaseQuery() :string {
		$dbCon = self::con()->db_con;
		return sprintf( "UPDATE `%s` as ri
						INNER JOIN `%s` as `sr`
							ON `ri`.id = `sr`.resultitem_ref
						INNER JOIN `%s` as `scans`
							ON `scans`.id = `sr`.scan_ref
						SET %%s
						WHERE %%s",
			$dbCon->scan_result_items->getTable(),
			$dbCon->scan_results->getTable(),
			$dbCon->scans->getTable()
		);
	}
}