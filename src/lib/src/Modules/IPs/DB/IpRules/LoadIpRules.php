<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\TableSchema;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\Common\BaseLoadRecordsForIPJoins;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModConsumer;

class LoadIpRules extends BaseLoadRecordsForIPJoins {

	use ModConsumer;

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
		return $this->mod()->getDbH_IPRules()->getTableSchema();
	}
}