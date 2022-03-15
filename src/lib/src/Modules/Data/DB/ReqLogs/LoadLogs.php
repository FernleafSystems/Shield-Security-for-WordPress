<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property int      $limit
 * @property int      $offset
 * @property string[] $wheres
 * @property string   $order_dir
 */
class LoadLogs {

	use ModConsumer;
	use IpAddressConsumer;

	/**
	 * @return LogRecord[]
	 */
	public function run() :array {
		$results = [];
		foreach ( $this->selectRaw() as $raw ) {
			$record = new LogRecord( $raw );
			$results[ $raw[ 'id' ] ] = $record;
		}
		return $results;
	}

	public function countAll() :int {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return (int)Services::WpDb()->getVar(
			sprintf( $this->getRawQuery(),
				'COUNT(*)',
				$mod->getDbH_ReqLogs()->getTableSchema()->table,
				$this->getCon()->getModule_Data()->getDbH_IPs()->getTableSchema()->table,
				empty( $wheres ) ? '' : 'WHERE '.implode( ' AND ', $wheres ),
				'',
				'',
				''
			)
		);
	}

	/**
	 * @return array[]
	 */
	private function selectRaw() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$selectFields = [
			'req.id',
			'req.req_id as rid',
			'req.meta',
			'req.created_at',
			'ips.ip as ip',
		];

		$wheres = $this->buildWheres();

		return Services::WpDb()->selectCustom(
			sprintf( $this->getRawQuery(),
				implode( ', ', $selectFields ),
				$mod->getDbH_ReqLogs()->getTableSchema()->table,
				$mod->getDbH_IPs()->getTableSchema()->table,
				empty( $wheres ) ? '' : 'WHERE '.implode( ' AND ', $wheres ),
				sprintf( 'ORDER BY `req`.created_at %s', $this->order_dir ?? 'DESC' ),
				isset( $this->limit ) ? sprintf( 'LIMIT %s', $this->limit ) : '',
				isset( $this->offset ) ? sprintf( 'OFFSET %s', $this->offset ) : ''
			)
		);
	}

	protected function buildWheres() :array {
		$wheres = is_array( $this->wheres ) ? $this->wheres : [];
		if ( !empty( $this->getIP() ) ) {
			$wheres[] = sprintf( "`ips`.ip=INET6_ATON('%s')", $this->getIP() );
		}
		return $wheres;
	}

	private function getRawQuery() :string {
		return 'SELECT %s
					FROM `%s` as `req`
					INNER JOIN `%s` as `ips`
						ON req.ip_ref = ips.id
					%s
					%s
					%s
					%s;';
	}
}