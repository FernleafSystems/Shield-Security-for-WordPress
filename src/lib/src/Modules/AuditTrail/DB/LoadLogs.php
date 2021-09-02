<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModCon;
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
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$stdKeys = array_flip( array_unique( array_merge(
			$mod->getDbH_Logs()
				->getTableSchema()
				->getColumnNames(),
			$this->getCon()
				 ->getModule_Plugin()
				 ->getDbH_IPs()
				 ->getTableSchema()
				 ->getColumnNames(),
			[
				'rid'
			]
		) ) );

		$results = [];

		foreach ( $this->selectRaw() as $raw ) {
			if ( empty( $results[ $raw[ 'id' ] ] ) ) {
				$record = new LogRecord( array_intersect_key( $raw, $stdKeys ) );
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
	 * @return array[]
	 */
	private function selectRaw() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$ip = $this->getIP();
		return Services::WpDb()->selectCustom(
			sprintf( 'SELECT log.id, log.site_id, log.event_slug, log.created_at,
							ips.ip as ip,
							meta.meta_key, meta.meta_value,
							req.req_id as rid
						FROM `%s` as log
						%s
						INNER JOIN `%s` as req
							ON log.req_ref = req.id 
						INNER JOIN `%s` as ips
							ON req.ip_ref = ips.id 
						LEFT JOIN `%s` as `meta`
							ON log.id = `meta`.log_ref 
						ORDER BY log.created_at DESC;',
				$mod->getDbH_Logs()->getTableSchema()->table,
				empty( $ip ) ? '' : sprintf( 'WHERE `ips.ip`="%s"', inet_pton( $ip ) ),
				$this->getCon()->getModule_Traffic()->getDbH_ReqLogs()->getTableSchema()->table,
				$this->getCon()->getModule_Plugin()->getDbH_IPs()->getTableSchema()->table,
				$mod->getDbH_Meta()->getTableSchema()->table
			)
		);
	}
}