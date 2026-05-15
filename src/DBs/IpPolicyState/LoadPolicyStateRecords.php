<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\IpPolicyState;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\TableSchema;

class LoadPolicyStateRecords extends \FernleafSystems\Wordpress\Plugin\Shield\DBs\Common\BaseLoadRecordsForIPJoins {

	/**
	 * @return Ops\Record[]
	 */
	public function select() :array {
		$results = [];
		foreach ( $this->selectRaw() as $raw ) {
			$results[ $raw[ 'id' ] ] = new Ops\Record( $raw );
		}
		return $results;
	}

	/**
	 * @throws \Exception
	 */
	public function loadRecord() :Ops\Record {
		$this->limit = 1;
		$records = $this->select();
		if ( empty( $records ) ) {
			throw new \Exception( __( 'No policy state record', 'wp-simple-firewall' ) );
		}
		return \current( $records );
	}

	protected function getJoinedTableAbbreviation() :string {
		return 'ips_state';
	}

	protected function getDefaultSelectFieldsForJoinedTable() :array {
		return $this->getTableSchemaForJoinedTable()->getColumnNames();
	}

	protected function getFallbackOrderByColumn() :string {
		return 'updated_at';
	}

	protected function getTableSchemaForJoinedTable() :TableSchema {
		return self::con()->db_con->ip_policy_state->getTableSchema();
	}
}
