<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\DBs;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Common\{
	IpAddressSql,
	SqlBackend
};
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class NoInet6AtonSqlUsageTest extends TestCase {

	protected function tearDown() :void {
		IpAddressSql::resetForTests();
		SqlBackend::resetForTests();
		parent::tearDown();
	}

	public function testMysqlModeUsesNativeInet6Aton() :void {
		SqlBackend::setSqliteOverrideForTests( false );

		$sql = IpAddressSql::equality( '`ips`.`ip`', '127.0.0.1' );

		$this->assertStringContainsString( 'INET6_ATON(', $sql );
		$this->assertSame( "`ips`.`ip`=INET6_ATON('127.0.0.1')", $sql );
	}

	public function testSqliteModeDoesNotUseInet6Aton() :void {
		SqlBackend::setSqliteOverrideForTests( true );

		$sql = IpAddressSql::equality( '`ips`.`ip`', '127.0.0.1' );

		$this->assertStringNotContainsString( 'INET6_ATON(', $sql );
		$this->assertSame( "`ips`.`ip`=X'7f000001'", $sql );
	}
}
