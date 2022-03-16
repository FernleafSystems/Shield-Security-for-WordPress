<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogTable;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BuildSearchPanesData {

	use ModConsumer;

	public function build() :array {
		return [
			'options' => [
				'ip'    => $this->buildForIPs(),
				'event' => $this->buildForEvents(),
			]
		];
	}

	private function buildForIPs() :array {
		$results = $this->runQuery( 'INET6_NTOA(ips.ip) as ip' );
		return array_filter( array_map(
			function ( $result ) {
				$ip = $result[ 'ip' ] ?? null;
				if ( !empty( $ip ) ) {
					$ip = [
						'label' => $ip,
						'value' => $ip,
					];
				}
				return $ip;
			},
			$results
		) );
	}

	private function buildForEvents() :array {
		$results = $this->runQuery( '`log`.event_slug as event' );
		return array_filter( array_map(
			function ( $result ) {
				$evt = $result[ 'event' ] ?? null;
				if ( !empty( $evt ) ) {
					$evt = [
						'label' => $this->getCon()->service_events->getEventName( $evt ),
						'value' => $evt,
					];
				}
				return $evt;
			},
			$results
		) );
	}

	private function runQuery( string $select ) :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$results = Services::WpDb()->selectCustom(
			sprintf( 'SELECT DISTINCT %s
						FROM `%s` as `log`
						INNER JOIN `%s` as req
							ON `log`.req_ref = req.id
						INNER JOIN `%s` as ips
							ON ips.id = req.ip_ref 
				',
				$select,
				$mod->getDbH_Logs()->getTableSchema()->table,
				$this->getCon()->getModule_Data()->getDbH_ReqLogs()->getTableSchema()->table,
				$this->getCon()->getModule_Data()->getDbH_IPs()->getTableSchema()->table
			)
		);
		return is_array( $results ) ? $results : [];
	}
}