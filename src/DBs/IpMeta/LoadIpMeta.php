<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\IpMeta;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\TableSchema;

class LoadIpMeta extends \FernleafSystems\Wordpress\Plugin\Shield\DBs\Common\BaseLoadRecordsForIPJoins {

	/**
	 * @return IpMetaRecord[]
	 */
	public function select() :array {
		$this->includeIpMeta = false;
		$results = [];
		foreach ( $this->selectRaw() as $raw ) {
			$results[ $raw[ 'id' ] ] = new IpMetaRecord( $raw );
		}
		return $results;
	}

	public function single( string $ip ) :?IpMetaRecord {
		$record = \current( $this->setIP( $ip )->select() );
		return empty( $record ) ? null : $record;
	}

	protected function getJoinedTableAbbreviation() :string {
		return 'im';
	}

	protected function getDefaultSelectFieldsForJoinedTable() :array {
		return [
			'id',
			'country_iso2',
			'asn',
			'pc_is_proxy',
			'pc_last_check_at',
			'updated_at',
		];
	}

	protected function getTableSchemaForJoinedTable() :TableSchema {
		return self::con()->db_con->ip_meta->getTableSchema();
	}
}