<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Ips\Options;
use FernleafSystems\Wordpress\Services\Services;

class Handler extends Base\Handler {

	public function autoCleanDb() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		/** @var Delete $del */
		$del = $this->getQueryDeleter();
		$del->filterByBlacklist()
			->filterByLastAccessBefore( Services::Request()->ts() - $opts->getAutoExpireTime() )
			->query();
	}

	/**
	 * @param int $nTimeStamp
	 * @return bool
	 */
	public function deleteRowsOlderThan( $nTimeStamp ) {
		return $this->getQueryDeleter()
					->addWhereOlderThan( $nTimeStamp, 'last_access_at' )
					->addWhere( 'list', ModCon::LIST_MANUAL_WHITE, '!=' )
					->query();
	}

	protected function getCustomColumns() :array {
		return $this->getOptions()->getDef( 'ip_list_table_columns' );
	}

	protected function getDefaultTableName() :string {
		return $this->getOptions()->getDef( 'ip_lists_table_name' );
	}

	protected function getTimestampColumns() :array {
		return $this->getOptions()->getDef( 'ip_list_table_timestamp_columns' );
	}
}