<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ActivityLog;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\SearchPanes\BuildDataForDays;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\SearchPanes\BuildDataForUsers;
use FernleafSystems\Wordpress\Services\Services;

class BuildSearchPanesData {

	use ModConsumer;

	public function build() :array {
		return [
			'options' => \array_map( '\array_values', [
				'day'   => $this->buildForDay(),
				'ip'    => $this->buildForIPs(),
				'event' => $this->buildForEvents(),
				'user'  => $this->buildForUsers(),
			] )
		];
	}

	private function buildForUsers() :array {
		return ( new BuildDataForUsers() )->build(
			\array_map(
				function ( $result ) {
					return (int)$result[ 'uid' ] ?? null;
				},
				$this->runQuery( '`req`.`uid` as `uid`' )
			)
		);
	}

	private function buildForDay() :array {
		$first = self::con()
			->db_con
			->dbhActivityLogs()
			->getQuerySelector()
			->setOrderBy( 'created_at', 'ASC' )
			->first();
		return ( new BuildDataForDays() )->buildFromOldestToNewest(
			empty( $first ) ? Services::Request()->ts() : $first->created_at
		);
	}

	private function buildForIPs() :array {
		return \array_values( \array_filter( \array_map(
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
			$this->runQuery( 'INET6_NTOA(`ips`.`ip`) as `ip`' )
		) ) );
	}

	private function buildForEvents() :array {
		return \array_values( \array_filter( \array_map(
			function ( $result ) {
				$evt = $result[ 'event' ] ?? null;
				if ( !empty( $evt ) ) {
					$evt = [
						'label' => self::con()->service_events->getEventName( $evt ),
						'value' => $evt,
					];
				}
				return $evt;
			},
			$this->runQuery( '`log`.`event_slug` as event' )
		) ) );
	}

	private function runQuery( string $select ) :array {
		$dbCon = self::con()->db_con;
		$results = Services::WpDb()->selectCustom(
			sprintf( 'SELECT DISTINCT %s
						FROM `%s` as `log`
						INNER JOIN `%s` as `req`
							ON `log`.`req_ref` = `req`.`id`
						INNER JOIN `%s` as `ips`
							ON `ips`.`id` = `req`.`ip_ref` 
				',
				$select,
				$dbCon->dbhActivityLogs()->getTableSchema()->table,
				$dbCon->dbhReqLogs()->getTableSchema()->table,
				$dbCon->dbhIPs()->getTableSchema()->table
			)
		);
		return \is_array( $results ) ? $results : [];
	}
}