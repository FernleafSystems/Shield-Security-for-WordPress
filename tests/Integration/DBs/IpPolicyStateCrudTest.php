<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\DBs;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy\{
	PolicyEvidence,
	PolicyState,
	PolicyStateRepository
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class IpPolicyStateCrudTest extends ShieldIntegrationTestCase {

	public function test_repository_read_for_unknown_ip_does_not_create_ip_record() :void {
		$this->requireDb( 'ips' );
		$this->requireDb( 'ip_policy_state' );

		$ip = '192.0.2.91';
		$ipRowsBefore = $this->countIpRows();
		$state = ( new PolicyStateRepository() )->forIp( $ip );

		$this->assertSame( 0, $state->ip_ref );
		$this->assertSame( PolicyState::BAND_NORMAL, $state->risk_band );
		$this->assertSame( $ipRowsBefore, $this->countIpRows() );

		try {
			( new IPRecords() )->loadIP( $ip, false, false );
			$this->fail( 'Policy-state read should not create an IP record.' );
		}
		catch ( \Exception ) {
			$this->assertSame( $ipRowsBefore, $this->countIpRows() );
		}
	}

	public function test_repository_read_for_empty_ip_does_not_select_existing_policy_state() :void {
		$this->requireDb( 'ips' );
		$this->requireDb( 'ip_policy_state' );

		$repository = new PolicyStateRepository();
		$seed = $repository->forIp( '192.0.2.92' );
		$seed->risk_band = PolicyState::BAND_HOSTILE;
		$seed->dirty = true;
		$this->assertTrue( $repository->save( $seed ) );
		$ipRowsBefore = $this->countIpRows();

		$empty = ( new PolicyStateRepository() )->forIp( '' );

		$this->assertSame( '', $empty->ip );
		$this->assertSame( 0, $empty->ip_ref );
		$this->assertNull( $empty->record_id );
		$this->assertSame( PolicyState::BAND_NORMAL, $empty->risk_band );
		$this->assertSame( $ipRowsBefore, $this->countIpRows() );
	}

	public function test_repository_save_for_invalid_ip_fails_without_creating_records() :void {
		$this->requireDb( 'ips' );
		$this->requireDb( 'ip_policy_state' );

		$ipRowsBefore = $this->countIpRows();
		$state = ( new PolicyStateRepository() )->forIp( 'not-an-ip' );
		$state->risk_band = PolicyState::BAND_SUSPICIOUS;
		$state->dirty = true;

		$this->assertFalse( ( new PolicyStateRepository() )->save( $state ) );
		$this->assertTrue( $state->dirty );
		$this->assertSame( 0, $state->ip_ref );
		$this->assertSame( $ipRowsBefore, $this->countIpRows() );
	}

	public function test_repository_persists_single_aggregate_record_per_ip() :void {
		$this->requireDb( 'ips' );
		$this->requireDb( 'ip_policy_state' );

		$ip = '192.0.2.90';
		$now = Services::Request()->ts();
		$repository = new PolicyStateRepository();
		$state = $repository->forIp( $ip );
		$this->assertSame( 0, $state->ip_ref );
		$state->risk_band = PolicyState::BAND_SUSPICIOUS;
		$state->last_evidence_at = $now;
		$state->expires_at = $now + DAY_IN_SECONDS;
		$state->meta = [
			'evidence' => [
				PolicyEvidence::TYPE_AUTH_ABUSE => [
					'15m' => [
						'started_at' => $now,
						'count'      => 2,
					],
				],
			],
		];
		$state->dirty = true;

		$this->assertTrue( $repository->save( $state ) );

		$reloaded = ( new PolicyStateRepository() )->forIp( $ip );
		$this->assertSame( PolicyState::BAND_SUSPICIOUS, $reloaded->risk_band );
		$this->assertSame( 2, $reloaded->counter( PolicyEvidence::TYPE_AUTH_ABUSE, '15m' ) );
		$this->assertSame( [
			'record_id',
			'ip',
			'ip_ref',
			'risk_band',
			'last_evidence_at',
			'expires_at',
			'meta',
			'dirty',
		], \array_keys( \get_object_vars( $reloaded ) ) );

		$reloaded->risk_band = PolicyState::BAND_HOSTILE;
		$reloaded->dirty = true;
		$this->assertTrue( ( new PolicyStateRepository() )->save( $reloaded ) );

		/** @var \FernleafSystems\Wordpress\Plugin\Shield\DBs\IpPolicyState\Ops\Select $selector */
		$selector = $this->requireController()->db_con->ip_policy_state->getQuerySelector();
		$ids = $selector->filterByIPRef( $reloaded->ip_ref )->setNoOrderBy()->all();
		$this->assertCount( 1, $ids );
		$this->assertSame( PolicyState::BAND_HOSTILE, ( new PolicyStateRepository() )->forIp( $ip )->risk_band );
	}

	private function countIpRows() :int {
		return (int)Services::WpDb()->getVar(
			sprintf( 'SELECT COUNT(*) FROM `%s`;', $this->requireController()->db_con->ips->getTableSchema()->table )
		);
	}
}
