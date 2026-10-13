<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\BotSignal\LoadBotSignalRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\{
	RuntimeTestState,
	TestDataFactory
};
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-type FixtureState array{
 *   ip:string,
 *   bot_signal_ids:list<int>,
 *   signal_snapshots:list<array<string,mixed>>,
 *   created_ip_ids:list<int>,
 *   options:array<string,mixed>
 * }
 * @phpstan-type FixtureContract array{ip:string}
 */
class NotBotAltchaFixtureBuilder {

	private const REQUIRED_DB_KEYS = [
		'ips',
		'bot_signals',
	];

	/**
	 * @return array{contract:FixtureContract,state:FixtureState}
	 */
	public function seed( string $ip = '' ) :array {
		RuntimeTestState::ensureDb( self::REQUIRED_DB_KEYS );

		$ip = $this->fixtureIp( $ip );
		$state = [
			'ip'             => $ip,
			'bot_signal_ids' => [],
			'signal_snapshots' => [],
			'created_ip_ids' => [],
			'options'        => RuntimeTestState::snapshotOptions( [ 'silentcaptcha_complexity', 'silentcaptcha_cookie_free' ] ),
		];

		try {
			RuntimeTestState::restoreOptions( [ 'silentcaptcha_complexity' => 'low', 'silentcaptcha_cookie_free' => 'N' ] );
			$this->seedIp( $ip, $state );

			return [
				'contract' => [
					'ip' => $ip,
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
	 * @return array{ip:string,notbot_at:int,altcha_at:int}
	 */
	public function inspect( array $state, string $ip = '' ) :array {
		RuntimeTestState::ensureDb( self::REQUIRED_DB_KEYS );
		$ip = $ip === '' ? (string)( $state[ 'ip' ] ?? '' ) : $ip;
		try {
			$record = ( new LoadBotSignalRecords() )->setIP( $ip )->loadRecord();
		}
		catch ( \Exception $e ) {
			$record = null;
		}

		return [
			'ip'        => $ip,
			'notbot_at' => (int)( $record->notbot_at ?? 0 ),
			'altcha_at' => (int)( $record->altcha_at ?? 0 ),
		];
	}

	public function addIp( array $state, string $ip ) :array {
		$ip = $this->fixtureIp( $ip );
		$this->seedIp( $ip, $state );
		return $state;
	}

	/** @phpstan-param FixtureState $state */
	private function seedIp( string $ip, array &$state ) :void {
		$existingIpId = $this->findIpId( $ip );
		$db = RuntimeTestState::controller()->db_con->bot_signals;
		$added = [ 'ip' => $ip, 'options' => [], 'bot_signal_ids' => [], 'signal_snapshots' => [], 'created_ip_ids' => [] ];
		try {
			if ( $existingIpId > 0 ) {
				$records = $db->getQuerySelector()->filterByIP( $existingIpId )->queryWithResult();
				foreach ( $records as $record ) {
					if ( !$db->getQueryDeleter()->deleteById( (int)$record->id ) ) {
						throw new \RuntimeException( 'Failed to isolate NotBot fixture signals.' );
					}
					$added[ 'signal_snapshots' ][] = $record->getRawData();
				}
			}
			$added[ 'bot_signal_ids' ][] = TestDataFactory::insertBotSignal( $ip, [ 'notbot_at' => 0, 'altcha_at' => 0 ] );
			$this->trackCreatedIpId( $ip, $existingIpId, $added );
		}
		catch ( \Throwable $throwable ) {
			$this->cleanup( $added );
			throw $throwable;
		}
		foreach ( [ 'bot_signal_ids', 'signal_snapshots', 'created_ip_ids' ] as $key ) {
			$state[ $key ] = \array_merge( $state[ $key ], $added[ $key ] );
		}
	}

	/**
	 * @phpstan-param FixtureState $state
	 */
	public function cleanup( array $state ) :void {
		RuntimeTestState::ensureDb( self::REQUIRED_DB_KEYS );
		$con = RuntimeTestState::controller();

		foreach ( $state[ 'bot_signal_ids' ] ?? [] as $botSignalId ) {
			if ( $botSignalId > 0 ) {
				$con->db_con->bot_signals->getQueryDeleter()->deleteById( $botSignalId );
			}
		}
		foreach ( $state[ 'created_ip_ids' ] ?? [] as $ipId ) {
			if ( $ipId > 0 ) {
				$con->db_con->ips->getQueryDeleter()->deleteById( $ipId );
			}
		}
		foreach ( $state[ 'signal_snapshots' ] ?? [] as $snapshot ) {
			$record = $con->db_con->bot_signals->getRecord()->applyFromArray( $snapshot );
			if ( !$con->db_con->bot_signals->getQueryInserter()->insert( $record ) ) {
				throw new \RuntimeException( 'Failed to restore NotBot fixture signals.' );
			}
		}
		if ( \is_array( $state[ 'options' ] ?? null ) ) {
			RuntimeTestState::restoreOptions( $state[ 'options' ] );
		}
	}

	private function fixtureIp( string $ip ) :string {
		$ip = \trim( $ip );
		if ( \filter_var( $ip, \FILTER_VALIDATE_IP ) !== false ) {
			return $ip;
		}

		$ip = \trim( (string)( RuntimeTestState::controller()->this_req->ip ?? '' ) );
		return $ip === '' ? Services::Request()->ip() : $ip;
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
