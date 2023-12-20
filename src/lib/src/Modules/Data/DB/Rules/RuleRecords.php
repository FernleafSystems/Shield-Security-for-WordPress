<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class RuleRecords {

	use ModConsumer;

	public function getLatestFirstDraft() :?Ops\Record {
		$dbh = self::con()->db_con->getDbH_Rules();

		// delete stale drafts
		Services::WpDb()->doSql( sprintf( 'DELETE FROM `%s` WHERE `form` IS NULL AND `updated_at`<%s;',
			$dbh->getTableSchema()->table,
			Services::Request()->ts() - MINUTE_IN_SECONDS*5
		) );

		/** @var Ops\Select $select */
		$select = $dbh->getQuerySelector();
		return $select->filterByEarlyDraft()->first();
	}
}