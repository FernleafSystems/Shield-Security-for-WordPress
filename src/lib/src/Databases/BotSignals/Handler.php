<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Handler extends Base\Handler {

	public function autoCleanDb() {
		$this->tableCleanExpired( $this->getOptions()->getDef( 'db_botsignals_autoexpire' ) );
	}

	public function getCustomColumns() :array {
		return $this->getOptions()->getDef( 'db_botsignals_columns' );
	}

	protected function getDefaultTableName() :string {
		return $this->getOptions()->getDef( 'db_botsignals_table' );
	}

	protected function getTimestampColumns() :array {
		return $this->getOptions()->getDef( 'db_botsignals_timestamp_columns' );
	}
}