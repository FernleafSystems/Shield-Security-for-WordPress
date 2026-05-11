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
 * @phpstan-type RequestLogEvidence array{
 *   exists:bool,
 *   id:int,
 *   rid:string,
 *   ip:string,
 *   path:string,
 *   verb:string,
 *   code:int,
 *   offense:bool,
 *   meta:array<string,string>
 * }
 * @phpstan-type ActivityEventEvidence array{
 *   exists:bool,
 *   id:int,
 *   event_slug:string,
 *   req_ref:int,
 *   request_rid:string
 * }
 * @phpstan-type FixtureInspection array{
 *   request_log:RequestLogEvidence,
 *   activity_event:ActivityEventEvidence
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
	 * @param array<string,mixed> $state
	 * @return FixtureInspection
	 */
	public function inspect( array $state ) :array {
		RuntimeTestState::ensureDb( self::REQUIRED_DB_KEYS );
		$state = $this->normalizePersistedState( $state );
		$con = RuntimeTestState::controller();

		$requestID = (int)( $state[ 'request_log_ids' ][ 0 ] ?? 0 );
		$activityID = (int)( $state[ 'activity_log_ids' ][ 0 ] ?? 0 );
		$requestRecord = $requestID > 0 ? $con->db_con->req_logs->getQuerySelector()->byId( $requestID ) : null;
		$activityRecord = $activityID > 0 ? $con->db_con->activity_logs->getQuerySelector()->byId( $activityID ) : null;

		return [
			'request_log'    => $this->requestLogEvidence( $requestRecord ),
			'activity_event' => $this->activityEventEvidence( $activityRecord, $requestRecord ),
		];
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
	 * @param array<string,mixed> $state
	 * @return FixtureState
	 */
	private function normalizePersistedState( array $state ) :array {
		return [
			'ip'               => \trim( (string)( $state[ 'ip' ] ?? '' ) ),
			'request_log_ids'  => $this->positiveIntList( $state[ 'request_log_ids' ] ?? [] ),
			'activity_log_ids' => $this->positiveIntList( $state[ 'activity_log_ids' ] ?? [] ),
			'created_ip_ids'   => $this->positiveIntList( $state[ 'created_ip_ids' ] ?? [] ),
		];
	}

	/**
	 * @return list<int>
	 */
	private function positiveIntList( $value ) :array {
		$values = \is_array( $value ) ? $value : [];
		return \array_values( \array_filter(
			\array_map( static fn( $id ) :int => (int)$id, $values ),
			static fn( int $id ) :bool => $id > 0
		) );
	}

	/**
	 * @param object|null $record
	 * @return RequestLogEvidence
	 */
	private function requestLogEvidence( ?object $record ) :array {
		return [
			'exists'  => $record !== null,
			'id'      => (int)( $record->id ?? 0 ),
			'rid'     => (string)( $record->req_id ?? '' ),
			'ip'      => $record === null ? '' : $this->requestLogIp( $record ),
			'path'    => (string)( $record->path ?? '' ),
			'verb'    => (string)( $record->verb ?? '' ),
			'code'    => (int)( $record->code ?? 0 ),
			'offense' => (bool)( $record->offense ?? false ),
			'meta'    => $this->decodeRequestMeta( $record->meta ?? '' ),
		];
	}

	private function requestLogIp( object $record ) :string {
		$ipRecord = RuntimeTestState::controller()->db_con->ips
			->getQuerySelector()
			->byId( (int)( $record->ip_ref ?? 0 ) );

		return \is_object( $ipRecord ) ? (string)( $ipRecord->ip ?? '' ) : '';
	}

	/**
	 * @param object|null $activityRecord
	 * @param object|null $requestRecord
	 * @return ActivityEventEvidence
	 */
	private function activityEventEvidence( ?object $activityRecord, ?object $requestRecord ) :array {
		return [
			'exists'      => $activityRecord !== null,
			'id'          => (int)( $activityRecord->id ?? 0 ),
			'event_slug'  => (string)( $activityRecord->event_slug ?? '' ),
			'req_ref'     => (int)( $activityRecord->req_ref ?? 0 ),
			'request_rid' => (string)( $requestRecord->req_id ?? '' ),
		];
	}

	/**
	 * @return array<string,string>
	 */
	private function decodeRequestMeta( $encodedMeta ) :array {
		$decoded = [];
		if ( \is_array( $encodedMeta ) ) {
			$decoded = $encodedMeta;
		}
		elseif ( \is_string( $encodedMeta ) && $encodedMeta !== '' ) {
			$json = \base64_decode( $encodedMeta, true );
			if ( \is_string( $json ) && $json !== '' ) {
				$maybeDecoded = \json_decode( $json, true );
				$decoded = \is_array( $maybeDecoded ) ? $maybeDecoded : [];
			}
		}

		$meta = [];
		foreach ( $decoded as $key => $value ) {
			if ( \is_scalar( $value ) ) {
				$meta[ (string)$key ] = (string)$value;
			}
		}
		\ksort( $meta );
		return $meta;
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
