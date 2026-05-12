<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Request;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;
use FernleafSystems\Wordpress\Services\Services;

class ThisRequestHighReputationBypassIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $requestSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		add_filter( 'shield/high_reputation_ip_minimum', [ $this, 'filterHighReputationMinimum' ] );
	}

	public function tear_down() {
		remove_filter( 'shield/high_reputation_ip_minimum', [ $this, 'filterHighReputationMinimum' ] );
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		parent::tear_down();
	}

	public function test_auto_blocked_high_reputation_ip_is_not_treated_as_auto_blocked() :void {
		$this->requireDb( 'ips' );
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'bot_signals' );

		$ip = '198.51.100.61';
		$now = Services::Request()->ts();
		$this->applyRequestForIp( $ip );

		TestDataFactory::insertAutoBlock( $ip );
		TestDataFactory::insertBotSignal( $ip, [
			'auth_at'   => $now - 60,
			'notbot_at' => $now - 30,
		] );

		$this->assertTrue( $this->requireController()->this_req->is_ip_high_reputation );
		$this->assertFalse( $this->requireController()->this_req->is_ip_blocked_shield_auto );
	}

	public function test_auto_blocked_low_reputation_ip_remains_auto_blocked() :void {
		$this->requireDb( 'ips' );
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'bot_signals' );

		$ip = '198.51.100.62';
		$this->applyRequestForIp( $ip );

		TestDataFactory::insertAutoBlock( $ip );

		$this->assertFalse( $this->requireController()->this_req->is_ip_high_reputation );
		$this->assertTrue( $this->requireController()->this_req->is_ip_blocked_shield_auto );
	}

	public function test_auto_block_filter_still_applies_after_high_reputation_bypass() :void {
		$this->requireDb( 'ips' );
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'bot_signals' );

		$ip = '198.51.100.63';
		$now = Services::Request()->ts();
		$this->applyRequestForIp( $ip );

		TestDataFactory::insertAutoBlock( $ip );
		TestDataFactory::insertBotSignal( $ip, [
			'auth_at'   => $now - 60,
			'notbot_at' => $now - 30,
		] );

		add_filter( 'shield/is_ip_blocked_auto', '__return_true' );
		try {
			$this->assertTrue( $this->requireController()->this_req->is_ip_high_reputation );
			$this->assertTrue( $this->requireController()->this_req->is_ip_blocked_shield_auto );
		}
		finally {
			remove_filter( 'shield/is_ip_blocked_auto', '__return_true' );
		}
	}


	public function filterHighReputationMinimum() :int {
		return 100;
	}

	private function applyRequestForIp( string $ip ) :void {
		$this->resetIpCaches();
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'GET',
			'REQUEST_URI'    => '/',
			'REMOTE_ADDR'    => $ip,
		] );
	}
}
