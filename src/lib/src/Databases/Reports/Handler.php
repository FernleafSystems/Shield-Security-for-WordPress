<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Handler extends Base\Handler {

	const TYPE_ALERT = 'alt';
	const TYPE_INFO = 'nfo';

	public function autoCleanDb() {
		$this->tableCleanExpired( $this->getTableSchema()->autoexpire );
	}

	protected function getDefaultTableName() :string {
		return $this->getTableSchema()->slug;
	}
}