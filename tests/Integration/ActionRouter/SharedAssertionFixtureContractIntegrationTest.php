<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops\Handler as IpRulesHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\IpAnalysisActivityMetaFixtureBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\IpRulesTableFixtureBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\SecurityHeadersFixtureBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class SharedAssertionFixtureContractIntegrationTest extends ShieldIntegrationTestCase {

	public function test_ip_analysis_fixture_inspection_uses_normalized_contract() :void {
		$this->ensureDefaultAdminUser();
		$builder = new IpAnalysisActivityMetaFixtureBuilder();
		$result = $builder->seed();

		try {
			$inspection = $builder->inspect( $result[ 'state' ] );

			$this->assertSame( [
				'exists'  => true,
				'id'      => $result[ 'state' ][ 'request_log_ids' ][ 0 ],
				'rid'     => $result[ 'contract' ][ 'rid' ],
				'ip'      => $result[ 'contract' ][ 'ip' ],
				'path'    => '/fixture/ip-analysis-meta',
				'verb'    => 'POST',
				'code'    => 418,
				'offense' => false,
				'meta'    => [
					'ts' => '1710000000',
					'ua' => 'IP Analysis Browser Fixture/1.0',
				],
			], $inspection[ 'request_log' ] );
			$this->assertSame( [
				'exists'      => true,
				'id'          => $result[ 'state' ][ 'activity_log_ids' ][ 0 ],
				'event_slug'  => 'user_login',
				'req_ref'     => $result[ 'state' ][ 'request_log_ids' ][ 0 ],
				'request_rid' => $result[ 'contract' ][ 'rid' ],
			], $inspection[ 'activity_event' ] );

			$mutatedState = $result[ 'state' ];
			$mutatedState[ 'ip' ] = '198.51.100.244';
			$this->assertSame(
				$result[ 'contract' ][ 'ip' ],
				$builder->inspect( $mutatedState )[ 'request_log' ][ 'ip' ],
				'Inspection should report the request-log DB IP, not the persisted fixture-state IP.'
			);

			$missingState = $result[ 'state' ];
			$missingState[ 'request_log_ids' ] = [ 999999999 ];
			$missingState[ 'activity_log_ids' ] = [ 999999999 ];
			$missingInspection = $builder->inspect( $missingState );
			$this->assertSame( [
				'exists'  => false,
				'id'      => 0,
				'rid'     => '',
				'ip'      => '',
				'path'    => '',
				'verb'    => '',
				'code'    => 0,
				'offense' => false,
				'meta'    => [],
			], $missingInspection[ 'request_log' ] );
			$this->assertSame( [
				'exists'      => false,
				'id'          => 0,
				'event_slug'  => '',
				'req_ref'     => 0,
				'request_rid' => '',
			], $missingInspection[ 'activity_event' ] );
		}
		finally {
			$builder->cleanup( $result[ 'state' ] );
		}
	}

	public function test_ip_rules_fixture_inspection_uses_normalized_contract() :void {
		$builder = new IpRulesTableFixtureBuilder();
		$result = $builder->seed();

		try {
			$inspection = $builder->inspect( $result[ 'state' ] );

			$this->assertSame( $result[ 'contract' ][ 'ip' ], $inspection[ 'ip' ] );
			$this->assertCount( 1, $inspection[ 'rules' ] );
			$this->assertSame( [
				'exists'       => true,
				'id'           => $result[ 'contract' ][ 'rule_id' ],
				'ip'           => $result[ 'contract' ][ 'ip' ],
				'type'         => IpRulesHandler::T_MANUAL_BLOCK,
				'cidr'         => 32,
				'is_range'     => false,
				'offenses'     => 0,
				'is_blocked'   => true,
				'label'        => 'browser-ip-rule-delete',
				'blocked_at'   => $inspection[ 'rules' ][ 0 ][ 'blocked_at' ],
				'unblocked_at' => 0,
			], $inspection[ 'rules' ][ 0 ] );
			$this->assertGreaterThan( 0, $inspection[ 'rules' ][ 0 ][ 'blocked_at' ] );
		}
		finally {
			$builder->cleanup( $result[ 'state' ] );
		}
	}

	public function test_security_headers_fixture_snapshots_contract_and_restores_options() :void {
		$optionKeys = [
			'global_enable_plugin_features',
			'x_frame',
			'x_xss_protect',
			'x_content_type',
			'x_referrer_policy',
		];
		$before = RuntimeTestState::snapshotOptions( $optionKeys );
		$builder = new SecurityHeadersFixtureBuilder();
		$result = $builder->seed();

		try {
			$this->assertSame( '/', $result[ 'contract' ][ 'path' ] );
			$this->assertSame( [
				'referrer-policy'         => 'no-referrer',
				'x-content-type-options' => 'nosniff',
				'x-frame-options'        => 'DENY',
				'x-xss-protection'       => '1; mode=block',
			], $result[ 'contract' ][ 'expected_headers' ] );
			$this->assertSame( [
				'global_enable_plugin_features' => 'Y',
				'x_frame'           => 'on_deny',
				'x_xss_protect'     => 'Y',
				'x_content_type'    => 'Y',
				'x_referrer_policy' => 'no-referrer',
			], $result[ 'contract' ][ 'options' ] );
		}
		finally {
			$builder->cleanup( $result[ 'state' ] );
		}

		$this->assertSame( $before, RuntimeTestState::snapshotOptions( $optionKeys ) );
	}

	private function ensureDefaultAdminUser() :void {
		if ( \get_user_by( 'login', 'admin' ) instanceof \WP_User ) {
			return;
		}

		self::factory()->user->create( [
			'user_login' => 'admin',
			'user_pass'  => 'password',
			'role'       => 'administrator',
		] );
	}
}
