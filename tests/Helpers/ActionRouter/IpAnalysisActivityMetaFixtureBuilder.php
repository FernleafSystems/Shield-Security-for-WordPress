<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\{
	RuntimeTestState,
	TestDataFactory
};

/**
 * @phpstan-type FixtureState array{
 *   ip:string,
 *   request_log_ids:list<int>,
 *   activity_log_ids:list<int>,
 *   created_ip_ids:list<int>
 * }
 * @phpstan-type FixtureContract array{
 *   ip:string,
 *   rid:string,
 *   expected_meta:list<string>
 * }
 */
class IpAnalysisActivityMetaFixtureBuilder {

	private const REQUIRED_DB_KEYS = [
		'ips',
		'req_logs',
		'activity_logs',
	];

	/**
	 * @return array{contract:FixtureContract,state:FixtureState}
	 */
	public function seed() :array {
		RuntimeTestState::loginAsSecurityAdmin();
		RuntimeTestState::ensureDb( self::REQUIRED_DB_KEYS );

		$state = $this->newFixtureState();

		try {
			$rid = 'fxmeta1234';
			$existingIpId = $this->findIpId( $state[ 'ip' ] );
			$requestId = TestDataFactory::insertRequestLog( $state[ 'ip' ], [
				'rid'  => $rid,
				'verb' => 'POST',
				'path' => '/fixture/ip-analysis-meta',
				'code' => 418,
				'meta' => [
					'ua' => 'IP Analysis Browser Fixture/1.0',
					'ts' => '1710000000',
				],
			] );
			$state[ 'request_log_ids' ][] = $requestId;
			$this->trackCreatedIpId( $state[ 'ip' ], $existingIpId, $state );

			$activityId = TestDataFactory::insertActivityLogForRequest( $requestId, 'user_login' );
			$state[ 'activity_log_ids' ][] = $activityId;

			return [
				'contract' => [
					'ip'            => $state[ 'ip' ],
					'rid'           => $rid,
					'expected_meta' => [
						'/fixture/ip-analysis-meta',
						'IP Analysis Browser Fixture/1.0',
						'418',
					],
				],
				'state'    => $state,
			];
		}
		catch ( \Throwable $throwable ) {
			$this->cleanup( $state );
			throw $throwable;
		}
	}

	/**
	 * @phpstan-param FixtureState $state
	 */
	public function cleanup( array $state ) :void {
		RuntimeTestState::ensureDb( self::REQUIRED_DB_KEYS );
		$con = RuntimeTestState::controller();

		foreach ( $state[ 'activity_log_ids' ] ?? [] as $activityId ) {
			if ( $activityId > 0 ) {
				$con->db_con->activity_logs->getQueryDeleter()->deleteById( $activityId );
			}
		}
		foreach ( $state[ 'request_log_ids' ] ?? [] as $requestId ) {
			if ( $requestId > 0 ) {
				$con->db_con->req_logs->getQueryDeleter()->deleteById( $requestId );
			}
		}
		foreach ( $state[ 'created_ip_ids' ] ?? [] as $ipId ) {
			if ( $ipId > 0 ) {
				$con->db_con->ips->getQueryDeleter()->deleteById( $ipId );
			}
		}

		\wp_set_current_user( 0 );
		RuntimeTestState::controller()->this_req->is_security_admin = false;
	}

	/**
	 * @return FixtureState
	 */
	private function newFixtureState() :array {
		return [
			'ip'              => '203.0.113.177',
			'request_log_ids' => [],
			'activity_log_ids' => [],
			'created_ip_ids'  => [],
		];
	}

	/**
	 * @phpstan-param FixtureState $state
	 */
	private function trackCreatedIpId( string $ip, int $existingIpId, array &$state ) :void {
		if ( $existingIpId < 1 ) {
			$createdIpId = $this->findIpId( $ip );
			if ( $createdIpId > 0 ) {
				$state[ 'created_ip_ids' ][] = $createdIpId;
			}
		}
	}

	private function findIpId( string $ip ) :int {
		$record = RuntimeTestState::controller()->db_con->ips
			->getQuerySelector()
			->filterByIPHuman( $ip )
			->setNoOrderBy()
			->first();

		return $record === null ? 0 : (int)$record->id;
	}
}
