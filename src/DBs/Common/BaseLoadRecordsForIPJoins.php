<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\Common;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\TableSchema;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property int      $limit
 * @property int      $offset
 * @property string[] $wheres
 * @property string   $order_by
 * @property string   $order_dir
 * @property string[] $ip_table_select_fields
 * @property string[] $joined_table_select_fields
 */
abstract class BaseLoadRecordsForIPJoins extends DynPropertiesClass {

	use PluginControllerConsumer;
	use IpAddressConsumer;

	protected bool $includeIpMeta = false;

	abstract public function select() :array;

	public function countAll() :int {
		$wheres = $this->buildWheres();
		return (int)Services::WpDb()->getVar(
			sprintf( $this->getRawQuery(),
				'COUNT(*)',
				$this->buildIpMetaSQL(),
				empty( $wheres ) ? '' : 'WHERE '.\implode( ' AND ', $wheres ),
				'',
				'',
				''
			)
		);
	}

	/**
	 * @return array[]
	 */
	protected function selectRaw() :array {
		$selectFields = \array_merge(
			$this->getSelectFieldsForIPTable(),
			\array_map(
				fn( string $field ) => sprintf( '`%s`.%s', $this->getJoinedTableAbbreviation(), $field ),
				$this->getSelectFieldsForJoinedTable()
			),
			\array_map(
				fn( string $field ) => sprintf( '`%s`.%s', 'ipm', $field ),
				$this->getSelectFieldsForIPMetaTable()
			)
		);

		$wheres = $this->buildWheres();

		return Services::WpDb()->selectCustom(
			sprintf( $this->getRawQuery(),
				\implode( ', ', $selectFields ),
				$this->buildIpMetaSQL(),
				empty( $wheres ) ? '' : 'WHERE '.\implode( ' AND ', $wheres ),
				$this->buildOrderBy(),
				isset( $this->limit ) ? sprintf( 'LIMIT %s', $this->limit ) : '',
				isset( $this->offset ) ? sprintf( 'OFFSET %s', $this->offset ) : ''
			)
		);
	}

	protected function buildIpMetaSQL() :string {
		return $this->includeIpMeta ?
			sprintf( 'LEFT JOIN `%s` as `ipm` ON `ips`.`id`=`ipm`.`ip_ref`',
				self::con()->db_con->ip_meta->getTableSchema()->table
			) : '';
	}

	protected function buildOrderBy() :string {
		$requestedOrderBy = \trim( (string)( $this->order_by ?? '' ) );
		if ( empty( $requestedOrderBy ) ) {
			return '';
		}

		$orderBy = $this->normaliseOrderByColumn( $requestedOrderBy );
		return empty( $orderBy ) ? ''
			: sprintf( 'ORDER BY `%s`.`%s` %s',
				$this->getJoinedTableAbbreviation(),
				$orderBy,
				$this->normaliseOrderDirection( (string)( $this->order_dir ?? '' ) )
			);
	}

	protected function getFallbackOrderByColumn() :string {
		return $this->getTableSchemaForJoinedTable()->getPrimaryKeyColumnName();
	}

	private function normaliseOrderByColumn( string $column ) :string {
		$columns = [];
		foreach ( $this->getTableSchemaForJoinedTable()->getColumnNames() as $schemaColumn ) {
			$schemaColumn = \trim( (string)$schemaColumn );
			if ( !empty( $schemaColumn ) ) {
				$columns[ \strtolower( $schemaColumn ) ] = $schemaColumn;
			}
		}

		$normalised = $columns[ \strtolower( \trim( $column ) ) ] ?? '';
		if ( !empty( $normalised ) ) {
			return $normalised;
		}

		$fallback = \trim( $this->getFallbackOrderByColumn() );
		return empty( $fallback ) ? '' : ( $columns[ \strtolower( $fallback ) ] ?? '' );
	}

	private function normaliseOrderDirection( string $direction ) :string {
		$direction = \strtoupper( \trim( $direction ) );
		return \in_array( $direction, [ 'ASC', 'DESC' ], true ) ? $direction : 'DESC';
	}

	protected function getDefaultSelectFieldsForJoinedTable() :array {
		return [];
	}

	protected function getSelectFieldsForJoinedTable() :array {
		$fields = \is_array( $this->joined_table_select_fields ) ? $this->joined_table_select_fields : $this->getDefaultSelectFieldsForJoinedTable();
		$fields[] = 'id'; // always include the ID
		return \array_unique( $fields );
	}

	protected function getDefaultSelectFieldsForIPTable() :array {
		return [
			'`ips`.`ip` as `ip`',
		];
	}

	protected function getSelectFieldsForIPTable() :array {
		$fields = \is_array( $this->ip_table_select_fields ) ? $this->ip_table_select_fields : $this->getDefaultSelectFieldsForIPTable();
		return \array_unique( $fields );
	}

	protected function getSelectFieldsForIPMetaTable() :array {
		return $this->includeIpMeta ? [
			'`asn`',
			'`country_iso2`',
			'`pc_is_proxy`',
			'`pc_last_check_at`',
		] : [];
	}

	protected function getJoinedTableAbbreviation() :string {
		return 'joined_table';
	}

	abstract protected function getTableSchemaForJoinedTable() :TableSchema;

	protected function buildWheres() :array {
		$wheres = \is_array( $this->wheres ) ? $this->wheres : [];
		if ( !empty( $this->getIP() ) ) {
			$wheres[] = IpAddressSql::equality( '`ips`.`ip`', $this->getIP() );
		}
		return $wheres;
	}

	protected function getRawQuery() :string {
		return sprintf( 'SELECT %%s
					FROM `%s` as `ips`
					INNER JOIN `%s` as `%s` ON `%s`.`ip_ref` = `ips`.`id`
					%%s /* LEFT JOIN ON IP META */
					%%s /* WHERE */
					%%s /* ORDER */
					%%s /* LIMIT */
					%%s; /* OFFSET */',
			self::con()->db_con->ips->getTableSchema()->table,
			$this->getTableSchemaForJoinedTable()->table,
			$this->getJoinedTableAbbreviation(),
			$this->getJoinedTableAbbreviation()
		);
	}
}
