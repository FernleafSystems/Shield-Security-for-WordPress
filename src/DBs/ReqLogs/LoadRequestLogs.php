<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\TableSchema;

class LoadRequestLogs extends \FernleafSystems\Wordpress\Plugin\Shield\DBs\Common\BaseLoadRecordsForIPJoins {

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
		return self::con()->db_con->req_logs->getTableSchema();
	}
}