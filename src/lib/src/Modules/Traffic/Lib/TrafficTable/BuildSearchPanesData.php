<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\TrafficTable;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BuildSearchPanesData {

	use ModConsumer;

	public function build() :array {
		return [
			'options' => [
				'ip'    => $this->buildForIPs(),
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

	private function runQuery( string $select ) :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$results = Services::WpDb()->selectCustom(
			sprintf( 'SELECT DISTINCT %s
						FROM `%s` as `req`
						INNER JOIN `%s` as ips
							ON ips.id = req.ip_ref 
				',
				$select,
				$mod->getDbH_ReqLogs()->getTableSchema()->table,
				$mod->getDbH_IPs()->getTableSchema()->table
			)
		);
		return is_array( $results ) ? $results : [];
	}
}