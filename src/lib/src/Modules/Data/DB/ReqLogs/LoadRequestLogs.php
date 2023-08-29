<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\TableSchema;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\Common\BaseLoadRecordsForIPJoins;

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
		return self::con()->getModule_Data()->getDbH_ReqLogs()->getTableSchema();
	}
}