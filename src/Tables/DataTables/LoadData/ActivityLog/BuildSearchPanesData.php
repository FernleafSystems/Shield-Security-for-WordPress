<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ActivityLog;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\SearchPanes\BuildDataForDays;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\SearchPanes\BuildDataForUsers;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildSearchPanesData;
use FernleafSystems\Wordpress\Services\Services;

class BuildSearchPanesData extends BaseBuildSearchPanesData {

	use PluginControllerConsumer;

	public function build() :array {
		return [
			'options' => \array_map( '\array_values', [
				'day'   => $this->buildForDay(),
				'event' => $this->buildForEvents(),
				'user'  => $this->buildForUsers(),
			] )
		];
	}

	private function buildForUsers() :array {
		return ( new BuildDataForUsers() )->build(
			\array_map(
				fn( $result ) => (int)$result[ 'uid' ] ?? null,
				$this->runDistinctUsersQuery()
			)
		);
	}

	private function buildForDay() :array {
		$first = self::con()
			->db_con
			->activity_logs
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
			$this->runDistinctIPsQuery()
		) ) );
	}

	private function buildForEvents() :array {
		return \array_values( \array_filter( \array_map(
			function ( $result ) {
				$evt = $result[ 'event' ] ?? null;
				if ( !empty( $evt ) ) {
					$label = self::con()->comps->events->getEventName( $evt );
					$evt = empty( $label ) ? null : [
						'label' => $label,
						'value' => $evt,
					];
				}
				return $evt;
			},
			$this->runDistinctEventsQuery()
		) ) );
	}

	protected function runDistinctEventsQuery() :array {
		$dbCon = self::con()->db_con;
		$results = Services::WpDb()->selectCustom(
			\sprintf( 'SELECT DISTINCT `event_slug` as `event` FROM `%s`',
				$dbCon->activity_logs->getTableSchema()->table
			)
		);
		return \is_array( $results ) ? $results : [];
	}

	protected function runDistinctUsersQuery() :array {
		$dbCon = self::con()->db_con;
		$results = Services::WpDb()->selectCustom(
			\sprintf( 'SELECT DISTINCT `req`.`uid` as `uid`
						FROM `%s` as `log`
						INNER JOIN `%s` as `req`
							ON `log`.`req_ref` = `req`.`id`',
				$dbCon->activity_logs->getTableSchema()->table,
				$dbCon->req_logs->getTableSchema()->table
			)
		);
		return \is_array( $results ) ? $results : [];
	}

	protected function runDistinctIPsQuery() :array {
		$dbCon = self::con()->db_con;
		$results = Services::WpDb()->selectCustom(
			\sprintf( 'SELECT DISTINCT INET6_NTOA(`ips`.`ip`) as `ip`
						FROM `%s` as `log`
						INNER JOIN `%s` as `req`
							ON `log`.`req_ref` = `req`.`id`
						INNER JOIN `%s` as `ips`
							ON `ips`.`id` = `req`.`ip_ref`',
				$dbCon->activity_logs->getTableSchema()->table,
				$dbCon->req_logs->getTableSchema()->table,
				$dbCon->ips->getTableSchema()->table
			)
		);
		return \is_array( $results ) ? $results : [];
	}
}