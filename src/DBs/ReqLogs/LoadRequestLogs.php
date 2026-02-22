<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\TableSchema;

class LoadRequestLogs extends \FernleafSystems\Wordpress\Plugin\Shield\DBs\Common\BaseLoadRecordsForIPJoins {

	public function forUserId( int $userId ) :self {
		if ( $userId > 0 ) {
			$wheres = \is_array( $this->wheres ) ? $this->wheres : [];
			$wheres[] = \sprintf( '`req`.`uid`=%d', $userId );
			$this->wheres = $wheres;
		}
		return $this;
	}

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
