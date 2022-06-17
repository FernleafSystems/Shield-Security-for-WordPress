<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\Common;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\TableSchema;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property int      $limit
 * @property int      $offset
 * @property string[] $wheres
 * @property string   $order_dir
 */
abstract class BaseLoadRecordsForIPJoins extends DynPropertiesClass {

	use ModConsumer;
	use IpAddressConsumer;

	abstract public function select() :array;

	public function countAll() :int {
		$wheres = $this->buildWheres();
		return (int)Services::WpDb()->getVar(
			sprintf( $this->getRawQuery(),
				'COUNT(*)',
				$this->getTableSchemaForJoinedTable()->table,
				$this->getCon()->getModule_Data()->getDbH_IPs()->getTableSchema()->table,
				empty( $wheres ) ? '' : 'WHERE '.implode( ' AND ', $wheres ),
				'',
				'',
				''
			)
		);
	}

	public function getDistinctIPs() :array {
		$results = Services::WpDb()->selectCustom(
			sprintf( 'SELECT DISTINCT INET6_NTOA(ips.ip) as ip
						FROM `%s` as `%s`
						INNER JOIN `%s` as `ips` ON `ips`.id = `%s`.ip_ref;',
				$this->getTableSchemaForJoinedTable()->table,
				$this->getJoinedTableAbbreviation(),
				$this->getCon()->getModule_Data()->getDbH_IPs()->getTableSchema()->table,
				$this->getJoinedTableAbbreviation()
			)
		);

		return array_values( array_filter( array_map(
			function ( $result ) {
				return is_array( $result ) ? ( $result[ 'ip' ] ?? null ) : null;
			},
			is_array( $results ) ? $results : []
		) ) );
	}

	/**
	 * @return array[]
	 */
	protected function selectRaw() :array {
		$modData = $this->getCon()->getModule_Data();

		$selectFields = array_merge(
			$this->getSelectFieldsForIPTable(),
			array_map(
				function ( string $field ) {
					return sprintf( '`%s`.%s', $this->getJoinedTableAbbreviation(), $field );
				},
				$this->getSelectFieldsForJoinedTable()
			)
		);

		$wheres = $this->buildWheres();

		return Services::WpDb()->selectCustom(
			sprintf( $this->getRawQuery(),
				implode( ', ', $selectFields ),
				$this->getTableSchemaForJoinedTable()->table,
				$modData->getDbH_IPs()->getTableSchema()->table,
				empty( $wheres ) ? '' : 'WHERE '.implode( ' AND ', $wheres ),
				sprintf( 'ORDER BY %s %s', $this->getOrderByColumn(), $this->order_dir ?? 'DESC' ),
				isset( $this->limit ) ? sprintf( 'LIMIT %s', $this->limit ) : '',
				isset( $this->offset ) ? sprintf( 'OFFSET %s', $this->offset ) : ''
			)
		);
	}

	protected function getOrderByColumn() :string {
		$sch = $this->getTableSchemaForJoinedTable();
		return sprintf( '`%s`.`%s`', $this->getJoinedTableAbbreviation(), $sch->has_created_at ? 'created_at' : 'id' );
	}

	protected function getSelectFieldsForJoinedTable() :array {
		return [];
	}

	protected function getSelectFieldsForIPTable() :array {
		return [
			'`ips`.`ip` as `ip`',
		];
	}

	protected function getJoinedTableAbbreviation() :string {
		return 'joined_table';
	}

	abstract protected function getTableSchemaForJoinedTable() :TableSchema;

	protected function buildWheres() :array {
		$wheres = is_array( $this->wheres ) ? $this->wheres : [];
		if ( !empty( $this->getIP() ) ) {
			$wheres[] = sprintf( "`ips`.ip=INET6_ATON('%s')", $this->getIP() );
		}
		return $wheres;
	}

	protected function getRawQuery() :string {
		$abbr = $this->getJoinedTableAbbreviation();
		return sprintf( 'SELECT %%s
					FROM `%%s` as `%s`
					INNER JOIN `%%s` as `ips`
						ON `%s`.ip_ref = `ips`.id
					%%s
					%%s
					%%s
					%%s;', $abbr, $abbr );
	}
}