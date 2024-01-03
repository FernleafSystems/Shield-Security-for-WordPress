<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class RuleRecords {

	use ModConsumer;

	public function getLatestFirstDraft() :?Ops\Record {
		$this->deleteOldDrafts();
		/** @var Ops\Select $select */
		$select = self::con()->db_con->getDbH_Rules()->getQuerySelector();
		return $select->filterByEarlyDraft()->first();
	}

	public function deleteOldDrafts() :void {
		Services::WpDb()->doSql( sprintf( 'DELETE FROM `%s` WHERE `form` IS NULL AND `updated_at`<%s;',
			self::con()->db_con->getDbH_Rules()->getTableSchema()->table,
			Services::Request()->ts() - MINUTE_IN_SECONDS*5
		) );
	}
}