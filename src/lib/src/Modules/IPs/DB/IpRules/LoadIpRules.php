<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\TableSchema;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\Common\BaseLoadRecordsForIPJoins;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;

class LoadIpRules extends BaseLoadRecordsForIPJoins {

	/**
	 * @return IpRuleRecord[]
	 */
	public function select() :array {
		$results = [];
		foreach ( $this->selectRaw() as $raw ) {
			$results[ $raw[ 'id' ] ] = new IpRuleRecord( $raw );
		}
		return $results;
	}

	protected function getJoinedTableAbbreviation() :string {
		return 'ir';
	}

	protected function getDefaultSelectFieldsForJoinedTable() :array {
		return $this->getTableSchemaForJoinedTable()->getColumnNames();
	}

	protected function getTableSchemaForJoinedTable() :TableSchema {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $mod->getDbH_IPRules()->getTableSchema();
	}
}