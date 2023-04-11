<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogTable;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\SearchPanes\BuildDataForDays;
use FernleafSystems\Wordpress\Services\Services;

class BuildSearchPanesData {

	use ModConsumer;

	public function build() :array {
		return [
			'options' => [
				'day'   => $this->buildForDay(),
				'ip'    => $this->buildForIPs(),
				'event' => $this->buildForEvents(),
//				'user'  => $this->buildForUser(),
			]
		];
	}

	private function buildForUser() :array {
		$WPDB = Services::WpDb();
		$results = $WPDB->selectCustom(
			sprintf( "SELECT DISTINCT `log_meta`.`meta_value` as `uid`
						FROM `%s` as `log_meta`
						WHERE `log_meta`.`meta_key`='uid'
				",
				$this->mod()->getDbH_Meta()->getTableSchema()->table
			)
		);
		$IDs = array_values( array_filter( array_map(
			function ( $result ) {
				return is_numeric( $result[ 'uid' ] ?? null ) ? (int)$result[ 'uid' ] : null;
			},
			is_array( $results ) ? $results : []
		) ) );

		$usersResult = $WPDB->selectCustom(
			sprintf( "SELECT `user_login`, `user_email`, `ID` as `id`
						FROM `%s` WHERE `id` IN (%s) limit 1000;",
				$WPDB->getTable_Users(),
				implode( ',', $IDs )
			)
		);

		$users = [];
		if ( is_array( $usersResult ) ) {
			foreach ( $usersResult as $user ) {
				$users[] = [
					'label' => sprintf( '%s (%s)', $user[ 'user_login' ], $user[ 'user_email' ] ),
					'value' => $user['id'],
				];
				$users[] = [
					'label' => sprintf( '%s (%s)', $user[ 'user_login' ], $user[ 'user_email' ] ),
					'value' => $user['id']+1,
				];
			}
		}

		return $users;
	}

	private function buildForDay() :array {
		return ( new BuildDataForDays() )->build(
			$this->mod()
				 ->getDbH_Logs()
				 ->getQuerySelector()
				 ->getDistinctForColumn( 'created_at' )
		);
	}

	private function buildForIPs() :array {
		return array_values( array_filter( array_map(
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
		return array_values( array_filter( array_map(
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
			$this->runQuery( '`log`.`event_slug` as event' )
		) ) );
	}

	private function runQuery( string $select ) :array {
		$results = Services::WpDb()->selectCustom(
			sprintf( 'SELECT DISTINCT %s
						FROM `%s` as `log`
						INNER JOIN `%s` as req
							ON `log`.req_ref = req.id
						INNER JOIN `%s` as ips
							ON ips.id = req.ip_ref 
				',
				$select,
				$this->mod()->getDbH_Logs()->getTableSchema()->table,
				$this->con()->getModule_Data()->getDbH_ReqLogs()->getTableSchema()->table,
				$this->con()->getModule_Data()->getDbH_IPs()->getTableSchema()->table
			)
		);
		return is_array( $results ) ? $results : [];
	}
}