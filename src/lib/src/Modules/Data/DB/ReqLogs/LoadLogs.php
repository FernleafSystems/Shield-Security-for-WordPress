<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\TableSchema;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\Common\BaseLoadRecordsForIPJoins;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\ModCon;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property int      $limit
 * @property int      $offset
 * @property string[] $wheres
 * @property string   $order_dir
 */
class LoadLogs extends BaseLoadRecordsForIPJoins {

	/**
	 * @return LogRecord[]
	 */
	public function run() :array {
		$results = [];
		foreach ( $this->selectRaw() as $raw ) {
			$record = new LogRecord( $raw );
			$results[ $raw[ 'id' ] ] = $record;
		}
		return $results;
	}

	protected function getJoinedTableAbbreviation() :string {
		return 'req';
	}

	protected function getSelectFieldsForJoinedTable() :array {
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

	public function countAll() :int {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$wheres = $this->buildWheres();
		return (int)Services::WpDb()->getVar(
			sprintf( $this->getRawQuery(),
				'COUNT(*)',
				$mod->getDbH_ReqLogs()->getTableSchema()->table,
				$this->getCon()->getModule_Data()->getDbH_IPs()->getTableSchema()->table,
				empty( $wheres ) ? '' : 'WHERE '.implode( ' AND ', $wheres ),
				'',
				'',
				''
			)
		);
	}
}