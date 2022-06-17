<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\CrowdSecDecisions;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\TableSchema;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\Common\BaseLoadRecordsForIPJoins;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;

class LoadCrowdsecDecisions extends BaseLoadRecordsForIPJoins {

	/**
	 * @return CrowdSecRecord[]
	 */
	public function all() :array {
		$results = [];
		foreach ( $this->selectRaw() as $raw ) {
			$results[ $raw[ 'id' ] ] = new CrowdSecRecord( $raw );
		}
		return $results;
	}

	protected function getJoinedTableAbbreviation() :string {
		return 'cs';
	}

	protected function getSelectFieldsForJoinedTable() :array {
		return [
			'id',
			'auto_unblock_at',
			'created_at',
		];
	}

	protected function getTableSchemaForJoinedTable() :TableSchema {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $mod->getDbH_CrowdSecDecisions()->getTableSchema();
	}
}