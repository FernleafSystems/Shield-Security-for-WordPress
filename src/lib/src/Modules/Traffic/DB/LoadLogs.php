<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\DB;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LoadLogs {

	use ModConsumer;
	use IpAddressConsumer;

	/**
	 * @return LogRecord[]
	 */
	public function run() :array {
		$stdKeys = array_flip( [
			'id',
			'rid',
			'ip',
			'created_at',
			'meta_key',
			'meta_value',
		] );

		$results = [];

		foreach ( $this->selectRaw() as $raw ) {

			if ( empty( $results[ $raw[ 'id' ] ] ) ) {
				$record = new LogRecord( array_intersect_key( $raw, $stdKeys ) );
				$results[ $raw[ 'id' ] ] = $record;
			}
			else {
				$record = $results[ $raw[ 'id' ] ];
			}

			$meta = $record->meta_data ?? [];
			$meta[ $raw[ 'meta_key' ] ] = $raw[ 'meta_value' ];
			$record->meta_data = $meta;
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
			sprintf( 'SELECT req.id, req.req_id as rid, req.created_at,
							ips.ip as ip,
							meta.meta_key, meta.meta_value
						FROM `%s` as `req`
						%s
						INNER JOIN `%s` as `ips`
							ON req.ip_ref = ips.id 
						INNER JOIN `%s` as `meta`
							ON `req`.id = meta.log_ref 
						ORDER BY `req`.created_at DESC;',
				$mod->getDbH_ReqLogs()->getTableSchema()->table,
				empty( $ip ) ? '' : sprintf( 'WHERE `ips.ip`="%s"', inet_pton( $ip ) ),
				$this->getCon()->getModule_Plugin()->getDbH_IPs()->getTableSchema()->table,
				$mod->getDbH_ReqMeta()->getTableSchema()->table
			)
		);
	}
}