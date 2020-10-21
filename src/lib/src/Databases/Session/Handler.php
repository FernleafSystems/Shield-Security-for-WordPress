<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Handler extends Base\Handler {

	public function autoCleanDb() {
		$this->tableCleanExpired( 30 );
	}

	protected function getCustomColumns() :array {
		return $this->getOptions()->getDef( 'sessions_table_columns' );
	}

	protected function getDefaultTableName() :string {
		return $this->getOptions()->getDef( 'sessions_table_name' );
	}

	protected function getTimestampColumns() :array {
		return $this->getOptions()->getDef( 'sessions_table_timestamp_columns' );
	}
}