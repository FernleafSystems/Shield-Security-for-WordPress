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

	/**
	 * @return array[]
	 */
	private function selectRaw() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$ip = $this->getIP();
		return Services::WpDb()->selectCustom(
			sprintf( 'SELECT req.id, req.req_id as rid, req.meta, req.created_at,
							ips.ip as ip
						FROM `%s` as `req`
						%s
						INNER JOIN `%s` as `ips`
							ON req.ip_ref = ips.id
						ORDER BY `req`.created_at %s
						%s
						%s;',
				$mod->getDbH_ReqLogs()->getTableSchema()->table,
				empty( $ip ) ? '' : sprintf( "WHERE `ips`.ip=INET6_ATON('%s')", $ip ),
				$this->getCon()->getModule_Data()->getDbH_IPs()->getTableSchema()->table,
				$this->order_dir ?? 'DESC',
				isset( $this->limit ) ? sprintf( 'LIMIT %s', $this->limit ) : '',
				isset( $this->offset ) ? sprintf( 'OFFSET %s', $this->offset ) : ''
			)
		);
	}
}