<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property int      $limit
 * @property int      $offset
 * @property string[] $wheres
 * @property string   $order_by
 * @property string   $order_dir
 */
class LoadLogs extends DynPropertiesClass {

	use PluginControllerConsumer;
	use IpAddressConsumer;

	private $includeMeta = true;

	/**
	 * @return LogRecord[]
	 */
	public function run( bool $includeMeta = true ) :array {
		$this->includeMeta = $includeMeta;

		$stdKeys = \array_flip( \array_unique( \array_merge(
			self::con()
				->db_con
				->activity_logs
				->getTableSchema()
				->getColumnNames(),
			self::con()
				->db_con
				->ips
				->getTableSchema()
				->getColumnNames(),
			[
				'rid'
			]
		) ) );

		$results = [];

		foreach ( $this->selectRaw() as $raw ) {
			if ( empty( $results[ $raw[ 'id' ] ] ) ) {
				$record = new LogRecord( \array_intersect_key( $raw, $stdKeys ) );
				$results[ $raw[ 'id' ] ] = $record;
			}
			else {
				$record = $results[ $raw[ 'id' ] ];
			}

			if ( !empty( $raw[ 'meta_key' ] ) ) {
				$meta = $record->meta_data ?? [];
				$meta[ $raw[ 'meta_key' ] ] = $raw[ 'meta_value' ];
				$record->meta_data = $meta;
			}
		}

		return $results;
	}

	/**
	 * https://stackoverflow.com/questions/55347251/cannot-select-where-ip-inet-ptonip
	 * We use MySQL built-in IP conversion, not PHPs, as it wasn't working as expected and return 0 results.
	 * Note: reverse is INET6_ATON
	 * @return array[]
	 */
	private function selectRaw() :array {
		$con = self::con();

		$selectFields = [
			'log.id',
			'log.site_id',
			'log.event_slug',
			'log.updated_at',
			'log.created_at',
			'ips.ip',
			'req.req_id as rid',
		];
		if ( $this->includeMeta ) {
			$selectFields = \array_merge( $selectFields, [
				'meta.meta_key',
				'meta.meta_value',
			] );
		}

		return Services::WpDb()->selectCustom(
			\sprintf( $this->getRawQuery( $this->includeMeta ),
				\implode( ', ', $selectFields ),
				$con->db_con->activity_logs->getTableSchema()->table,
				$con->db_con->req_logs->getTableSchema()->table,
				$con->db_con->ips->getTableSchema()->table,
				empty( $this->getIP() ) ? '' : \sprintf( "AND ips.ip=INET6_ATON('%s')", $this->getIP() ),
				$this->includeMeta ? $con->db_con->activity_logs_meta->getTable() : '',
				empty( $this->wheres ) ? '' : 'WHERE '.\implode( ' AND ', $this->wheres ),
				$this->buildOrderBy(),
				isset( $this->limit ) ? \sprintf( 'LIMIT %s', $this->limit ) : '',
				isset( $this->offset ) ? \sprintf( 'OFFSET %s', $this->offset ) : ''
			)
		);
	}

	private function buildOrderBy() :string {
		return \sprintf( 'ORDER BY `log`.`updated_at` %s', $this->order_dir ?? 'DESC' );
	}

	public function countAll() :int {
		$con = self::con();
		return (int)Services::WpDb()->getVar(
			\sprintf( $this->getRawQuery( false ),
				'COUNT(*)',
				$con->db_con->activity_logs->getTable(),
				$con->db_con->req_logs->getTable(),
				$con->db_con->ips->getTable(),
				empty( $this->getIP() ) ? '' : \sprintf( "AND ips.ip=INET6_ATON('%s')", $this->getIP() ),
				'',
				empty( $this->wheres ) ? '' : 'WHERE '.\implode( ' AND ', $this->wheres ),
				'',
				'',
				''
			)
		);
	}

	private function getRawQuery( bool $includeMeta = true ) :string {
		return \sprintf( 'SELECT %%s
					FROM `%%s` as log
					INNER JOIN `%%s` as `req`
						ON log.req_ref = `req`.id
					INNER JOIN `%%s` as `ips`
						ON `ips`.id = `req`.ip_ref 
						%%s
					%s
					%%s
					%%s
					%%s
					%%s
				',
			$includeMeta ? 'LEFT JOIN `%s` as `meta` ON log.id = `meta`.log_ref' : '%s'
		);
	}
}