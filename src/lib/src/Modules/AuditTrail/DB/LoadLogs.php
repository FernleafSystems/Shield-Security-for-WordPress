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
				 ->getModule_Data()
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
	 * https://stackoverflow.com/questions/55347251/cannot-select-where-ip-inet-ptonip
	 * We use MySQL built-in IP conversion, not PHPs, as it wasn't working as expected and return 0 results.
	 * Note: reverse is INET6_ATON
	 * @return array[]
	 */
	private function selectRaw() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		return Services::WpDb()->selectCustom(
			sprintf( 'SELECT log.id, log.site_id, log.event_slug, log.created_at,
							ips.ip,
							meta.meta_key, meta.meta_value,
							req.req_id as rid
						FROM `%s` as log
						INNER JOIN `%s` as req
							ON log.req_ref = req.id
						INNER JOIN `%s` as ips
							ON ips.id = req.ip_ref 
							%s
						LEFT JOIN `%s` as `meta`
							ON log.id = `meta`.log_ref
						ORDER BY log.updated_at DESC;',
				$mod->getDbH_Logs()->getTableSchema()->table,
				$this->getCon()->getModule_Data()->getDbH_ReqLogs()->getTableSchema()->table,
				$this->getCon()->getModule_Data()->getDbH_IPs()->getTableSchema()->table,
				empty( $this->getIP() ) ? '' : sprintf( "AND ips.ip=INET6_ATON('%s')", $this->getIP() ),
				$mod->getDbH_Meta()->getTableSchema()->table
			)
		);
	}
}