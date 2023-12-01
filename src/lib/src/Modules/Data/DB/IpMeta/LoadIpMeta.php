<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IpMeta;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\TableSchema;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\Common\BaseLoadRecordsForIPJoins;

class LoadIpMeta extends BaseLoadRecordsForIPJoins {

	/**
	 * @return IpMetaRecord[]
	 */
	public function select() :array {
		$results = [];
		foreach ( $this->selectRaw() as $raw ) {
			$results[ $raw[ 'id' ] ] = new IpMetaRecord( $raw );
		}
		return $results;
	}

	protected function getJoinedTableAbbreviation() :string {
		return 'im';
	}

	protected function getDefaultSelectFieldsForJoinedTable() :array {
		return [
			'id',
			'pc_is_proxy',
			'pc_last_check_at',
		];
	}

	protected function getTableSchemaForJoinedTable() :TableSchema {
		return self::con()->getModule_Data()->getDbH_IPMeta()->getTableSchema();
	}
}