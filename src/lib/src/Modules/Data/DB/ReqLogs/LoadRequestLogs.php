<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\TableSchema;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\Common\BaseLoadRecordsForIPJoins;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\ModCon;

class LoadRequestLogs extends BaseLoadRecordsForIPJoins {

	/**
	 * @return LogRecord[]
	 */
	public function select() :array {
		$results = [];
		foreach ( $this->selectRaw() as $raw ) {
			$results[ $raw[ 'id' ] ] = new LogRecord( $raw );
		}
		return $results;
	}

	protected function getJoinedTableAbbreviation() :string {
		return 'req';
	}

	protected function getDefaultSelectFieldsForJoinedTable() :array {
		return [
			'id',
			'req_id as rid',
			'uid',
			'type',
			'path',
			'code',
			'verb',
			'meta',
			'offense',
			'created_at',
		];
	}

	protected function getTableSchemaForJoinedTable() :TableSchema {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $mod->getDbH_ReqLogs()->getTableSchema();
	}
}