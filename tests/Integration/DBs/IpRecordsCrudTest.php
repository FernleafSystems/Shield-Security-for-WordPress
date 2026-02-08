<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\DBs;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class IpRecordsCrudTest extends ShieldIntegrationTestCase {

	public function test_loadIP_auto_creates_record() {
		$this->requireDb( 'ips' );

		$records = new IPRecords();
		$record = $records->loadIP( '192.0.2.1' );

		$this->assertNotEmpty( $record );
		$this->assertSame( '192.0.2.1', $record->ip );
	}

	public function test_loadIP_without_auto_create_throws_for_unknown() {
		$this->requireDb( 'ips' );

		$this->expectException( \Exception::class );
		( new IPRecords() )->loadIP( '198.51.100.99', false );
	}

	public function test_loadIP_returns_cached_instance_on_second_call() {
		$this->requireDb( 'ips' );

		$records = new IPRecords();
		$first = $records->loadIP( '192.0.2.2' );
		$second = $records->loadIP( '192.0.2.2' );

		$this->assertSame( $first, $second, 'Subsequent loadIP() should return the cached object reference.' );
	}

	public function test_loadIP_normalises_ip_format() {
		$this->requireDb( 'ips' );

		$records = new IPRecords();
		$record = $records->loadIP( '192.0.2.3/32' );

		$this->assertNotEmpty( $record );
		// The stored IP should be the normalised address without CIDR suffix.
		$this->assertSame( '192.0.2.3', $record->ip );
	}

	public function test_loadIP_throws_for_invalid_ip() {
		$this->requireDb( 'ips' );

		$this->expectException( \Exception::class );
		( new IPRecords() )->loadIP( 'not-an-ip' );
	}
}
