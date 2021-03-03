<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Handler extends Base\Handler {

	public function autoCleanDb() {
		$this->tableCleanExpired( (int)$this->getTableSchema()->autoexpire );
	}

	protected function getDefaultTableName() :string {
		return $this->getTableSchema()->slug;
	}
}