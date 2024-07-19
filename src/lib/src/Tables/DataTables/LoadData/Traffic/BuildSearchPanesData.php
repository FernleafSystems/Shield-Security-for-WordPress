<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\{
	LoadRequestLogs,
	Ops as ReqLogsDB
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\SearchPanes\BuildDataForDays;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\SearchPanes\BuildDataForUsers;
use FernleafSystems\Wordpress\Services\Services;

class BuildSearchPanesData {

	use PluginControllerConsumer;

	private $distinctQueryResult = null;

	public function build() :array {
		return [
			'options' => \array_map( '\array_values', [
				'day'     => $this->buildForDay(),
				'ip'      => $this->buildForIPs(),
				'type'    => $this->buildForType(),
				'offense' => $this->buildForOffense(),
				'code'    => $this->buildForCodes(),
				'user'    => $this->buildForUsers(),
			] )
		];
	}

	private function buildForDay() :array {
		$first = self::con()
			->db_con
			->req_logs
			->getQuerySelector()
			->setOrderBy( 'created_at', 'ASC' )
			->first();
		return ( new BuildDataForDays() )->buildFromOldestToNewest(
			empty( $first ) ? Services::Request()->ts() : $first->created_at
		);
	}

	private function buildForUsers() :array {
		return ( new BuildDataForUsers() )->build( $this->getDistinctQueryResult()[ 'uid' ] ?? [] );
	}

	protected function getDistinctQueryResult() :array {
		if ( \is_null( $this->distinctQueryResult ) ) {
			$this->distinctQueryResult = \array_map( function ( $raw ) {
				return \explode( ',', $raw );
			}, $this->compositeDistinctQuery( [ 'type', 'code', 'uid' ] ) );
		}
		return $this->distinctQueryResult;
	}

	private function buildForCodes() :array {
		return \array_values( \array_filter( \array_map(
			function ( $code ) {
				if ( empty( $code ) ) {
					return null;
				}
				return [
					'label' => $code,
					'value' => $code,
				];
			},
			$this->getDistinctQueryResult()[ 'code' ] ?? []
		) ) );
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
		return \array_values( \array_filter( \array_map(
			function ( $type ) {
				if ( empty( $type ) ) {
					return null;
				}
				return [
					'label' => ReqLogsDB\Handler::GetTypeName( $type ),
					'value' => $type,
				];
			},
			$this->getDistinctQueryResult()[ 'type' ] ?? []
		) ) );
	}

	private function buildForIPs() :array {
		return \array_map(
			function ( $ip ) {
				return [
					'label' => $ip,
					'value' => $ip,
				];
			},
			( new LoadRequestLogs() )->getDistinctIPs()
		);
	}

	/**
	 * https://stackoverflow.com/questions/12188027/mysql-select-distinct-multiple-columns#answer-12188117
	 */
	private function compositeDistinctQuery( array $columns ) :array {
		$results = Services::WpDb()->selectCustom( sprintf( 'SELECT %s',
				\implode( ', ', \array_map( function ( $col ) {
					return sprintf( '(SELECT group_concat(DISTINCT %s) FROM %s) as %s',
						$col, self::con()->db_con->req_logs->getTableSchema()->table, $col );
				}, $columns ) ) )
		);
		return empty( $results ) ? [] : \array_filter( $results[ 0 ] );
	}
}