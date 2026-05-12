<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\{
	RuntimeTestState,
	TestDataFactory
};
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-type FixtureState array{
 *   ip:string,
 *   bot_signal_ids:list<int>,
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
			'created_ip_ids' => [],
			'options'        => RuntimeTestState::snapshotOptions( [ 'silentcaptcha_complexity' ] ),
		];

		try {
			$existingIpId = $this->findIpId( $ip );
			RuntimeTestState::restoreOptions( [ 'silentcaptcha_complexity' => 'low' ] );
			$state[ 'bot_signal_ids' ][] = TestDataFactory::insertBotSignal( $ip, [
				'notbot_at' => 0,
				'altcha_at' => 0,
			] );
			$this->trackCreatedIpId( $ip, $existingIpId, $state );

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
	public function inspect( array $state ) :array {
		RuntimeTestState::ensureDb( self::REQUIRED_DB_KEYS );
		$record = $this->loadBotSignalRecord( $state );

		return [
			'ip'        => (string)( $state[ 'ip' ] ?? '' ),
			'notbot_at' => (int)( $record->notbot_at ?? 0 ),
			'altcha_at' => (int)( $record->altcha_at ?? 0 ),
		];
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
	 * @return object|null
	 */
	private function loadBotSignalRecord( array $state ) {
		$botSignalIds = $state[ 'bot_signal_ids' ] ?? [];
		$botSignalId = \is_array( $botSignalIds ) ? (int)( \end( $botSignalIds ) ?: 0 ) : 0;
		if ( $botSignalId > 0 ) {
			return RuntimeTestState::controller()->db_con->bot_signals
				->getQuerySelector()
				->byId( $botSignalId );
		}
		return null;
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
