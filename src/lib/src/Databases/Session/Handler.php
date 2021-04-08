<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Handler extends Base\Handler {

	public function autoCleanDb() {
		$this->tableCleanExpired( 30 );
	}

	/**
	 * @return string
	 * @deprecated 11.1
	 */
	protected function getDefaultTableName() :string {
		return $this->getOptions()->getDef( 'sessions_table_name' );
	}
}