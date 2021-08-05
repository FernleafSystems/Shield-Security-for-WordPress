<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LoadLogs {

	use ModConsumer;

	/**
	 * @return LogRecord[]
	 */
	public function run() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$stdKeys = array_flip( $mod->getDbH_Logs()
								   ->getTableSchema()
								   ->getColumnNames() );

		$results = [];

		foreach ( $this->selectRaw() as $raw ) {
			if ( empty( $results[ $raw[ 'id' ] ] ) ) {
				$record = new LogRecord( array_intersect_key( $raw, $stdKeys ) );
				$results[ $raw[ 'id' ] ] = $record;
			}
			else {
				$record = $results[ $raw[ 'id' ] ];
			}

			$meta = $record->meta_data ?? [];
			$meta[ $raw[ 'meta_key' ] ] = $raw[ 'meta_value' ];
			$record->meta_data = $meta;
		}

		return $results;
	}

	/**
	 * @return array[]
	 */
	private function selectRaw() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbhLogs = $mod->getDbH_Logs();
		$dbhMeta = $mod->getDbH_Meta();
		return Services::WpDb()->selectCustom(
			sprintf( 'SELECT  log.*, meta.meta_key, meta.meta_value, meta.log_ref as id
						FROM `%s` as log
						INNER JOIN `%s` as meta
							ON log.id = meta.log_ref 
						ORDER BY log.id DESC;',
				$dbhLogs->getTableSchema()->table,
				$dbhMeta->getTableSchema()->table
			)
		);
	}
}