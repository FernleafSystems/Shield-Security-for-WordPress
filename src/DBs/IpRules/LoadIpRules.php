<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\TableSchema;

class LoadIpRules extends \FernleafSystems\Wordpress\Plugin\Shield\DBs\Common\BaseLoadRecordsForIPJoins {

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
		return self::con()->db_con->ip_rules->getTableSchema();
	}
}