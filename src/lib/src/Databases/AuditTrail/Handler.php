<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Options;

class Handler extends Base\Handler {

	public function autoCleanDb() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$this->tableCleanExpired( $opts->getAutoCleanDays() );
		$this->tableTrimExcess( $opts->getMaxEntries() );
	}

	public function getCustomColumns() :array {
		return $this->getOptions()->getDef( 'audit_trail_table_columns' );
	}

	protected function getDefaultTableName() :string {
		return $this->getOptions()->getDef( 'audit_trail_table_name' );
	}

	protected function getTimestampColumns() :array {
		return $this->getOptions()->getDef( 'audittrail_table_timestamp_columns' );
	}
}