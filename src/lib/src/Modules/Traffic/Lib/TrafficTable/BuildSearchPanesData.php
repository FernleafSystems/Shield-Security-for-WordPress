<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\TrafficTable;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BuildSearchPanesData {

	use ModConsumer;

	public function build() :array {
		return [
			'options' => [
				'ip'      => $this->buildForIPs(),
				'type'    => $this->buildForType(),
				'offense' => $this->buildForOffense(),
				'code'    => $this->buildForCodes(),
			]
		];
	}

	private function buildForCodes() :array {
		$results = $this->runQuery( 'code as code', false );
		return array_filter( array_map(
			function ( $result ) {
				$code = $result[ 'code' ] ?? null;
				if ( !empty( $code ) ) {
					$code = [
						'label' => $code,
						'value' => $code,
					];
				}
				return $code;
			},
			$results
		) );
	}

	private function buildForOffense() :array {
		return [
			[
				'label' => __( 'Offense', 'wp-simple-firewall' ),
				'value' => 1,
			],
			[
				'label' => __( 'Not Offense', 'wp-simple-firewall' ),
				'value' => 0,
			]
		];
	}

	private function buildForType() :array {
		$results = $this->runQuery( 'type as type', false );
		return array_filter( array_map(
			function ( $result ) {
				$type = $result[ 'type' ] ?? null;
				if ( !empty( $type ) ) {
					$type = [
						'label' => Handler::GetTypeName( $type ),
						'value' => $type,
					];
				}
				return $type;
			},
			$results
		) );
	}

	private function buildForIPs() :array {
		$results = $this->runQuery( 'INET6_NTOA(ips.ip) as ip', true );
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

	private function runQuery( string $select, bool $joinWithIPs ) :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$results = Services::WpDb()->selectCustom(
			sprintf( 'SELECT DISTINCT %s
						FROM `%s` as `req`
						%s;',
				$select,
				$mod->getDbH_ReqLogs()->getTableSchema()->table,
				$joinWithIPs ? sprintf( 'INNER JOIN `%s` as ips ON ips.id = req.ip_ref',
					$mod->getDbH_IPs()->getTableSchema()->table ) : ''

			)
		);
		return is_array( $results ) ? $results : [];
	}
}