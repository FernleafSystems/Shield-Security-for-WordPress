<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Sessions\SessionActivityPersistencePolicy;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class SessionActivityPersistencePolicyTest extends BaseUnitTest {

	public function test_missing_shield_metadata_persists_immediately() :void {
		$policy = new SessionActivityPersistencePolicy();

		$this->assertTrue( $policy->shouldPersist(
			[
				'ip'         => '203.0.113.10',
				'login'      => 100,
				'expiration' => 1000,
			],
			[
				'ip'         => '203.0.113.10',
				'login'      => 100,
				'expiration' => 1000,
				'shield'     => [
					'user_id'          => 7,
					'last_activity_at' => 200,
				],
			],
			200
		) );
	}

	public function test_stable_identity_field_change_persists_immediately() :void {
		$policy = new SessionActivityPersistencePolicy();

		$this->assertTrue( $policy->shouldPersist(
			$this->sessionWithShield( [
				'ip'               => '203.0.113.10',
				'last_activity_at' => 200,
			] ),
			$this->sessionWithShield( [
				'ip'               => '203.0.113.11',
				'last_activity_at' => 230,
			] ),
			230
		) );
	}

	public function test_recent_routine_activity_is_not_persisted() :void {
		$policy = new SessionActivityPersistencePolicy();

		$this->assertFalse( $policy->shouldPersist(
			$this->sessionWithShield( [
				'last_activity_at' => 200,
			] ),
			$this->sessionWithShield( [
				'idle_interval'    => 30,
				'last_activity_at' => 230,
			] ),
			230
		) );
	}

	public function test_routine_activity_at_interval_boundary_is_persisted() :void {
		$policy = new SessionActivityPersistencePolicy();

		$this->assertTrue( $policy->shouldPersist(
			$this->sessionWithShield( [
				'last_activity_at' => 200,
			] ),
			$this->sessionWithShield( [
				'idle_interval'    => SessionActivityPersistencePolicy::ACTIVITY_PERSIST_INTERVAL,
				'last_activity_at' => 260,
			] ),
			260
		) );
	}

	private function sessionWithShield( array $shieldOverrides = [] ) :array {
		return [
			'ip'         => '203.0.113.10',
			'ua'         => 'Unit Test',
			'login'      => 100,
			'expiration' => 1000,
			'shield'     => \array_merge( [
				'user_id'            => 7,
				'expires_at'         => 1000,
				'host'               => 'example.org',
				'unique'             => 'abc123',
				'useragent'          => 'Unit Test',
				'ip'                 => '203.0.113.10',
				'session_started_at' => 100,
				'token_started_at'   => 100,
				'idle_interval'      => 0,
				'last_activity_at'   => 200,
				'session_duration'   => 100,
				'token_duration'     => 100,
			], $shieldOverrides ),
		];
	}
}
