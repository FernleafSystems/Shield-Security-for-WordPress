<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\DBs;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Common\{
	IpAddressSql,
	SqlBackend
};
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class IpAddressSqlTest extends TestCase {

	protected function tearDown() :void {
		IpAddressSql::resetForTests();
		SqlBackend::resetForTests();
		parent::tearDown();
	}

	public function testMysqlLiteralFromIpv4UsesNativeFunction() :void {
		SqlBackend::setSqliteOverrideForTests( false );

		$this->assertSame(
			"INET6_ATON('127.0.0.1')",
			IpAddressSql::literalFromIp( '127.0.0.1' )
		);
	}

	public function testMysqlLiteralFromIpv6UsesNativeFunction() :void {
		SqlBackend::setSqliteOverrideForTests( false );

		$this->assertSame(
			"INET6_ATON('::1')",
			IpAddressSql::literalFromIp( '::1' )
		);
	}

	public function testMysqlLiteralForInvalidIpStillUsesNativeFunction() :void {
		SqlBackend::setSqliteOverrideForTests( false );

		$this->assertSame(
			"INET6_ATON('not-an-ip')",
			IpAddressSql::literalFromIp( 'not-an-ip' )
		);
	}

	public function testMysqlLiteralEscapesSingleQuotesInInput() :void {
		SqlBackend::setSqliteOverrideForTests( false );

		$this->assertSame(
			"INET6_ATON('bad''ip')",
			IpAddressSql::literalFromIp( "bad'ip" )
		);
	}

	public function testMysqlEqualityUsesNativeFunction() :void {
		SqlBackend::setSqliteOverrideForTests( false );

		$this->assertSame(
			"`ips`.`ip`=INET6_ATON('127.0.0.1')",
			IpAddressSql::equality( '`ips`.`ip`', '127.0.0.1' )
		);
	}

	public function testMysqlLiteralsFromIpsRetainsAllStringInputsInOrder() :void {
		SqlBackend::setSqliteOverrideForTests( false );

		$this->assertSame(
			[
				"INET6_ATON('127.0.0.1')",
				"INET6_ATON('not-an-ip')",
				"INET6_ATON('::1')",
			],
			IpAddressSql::literalsFromIps( [
				'127.0.0.1',
				'not-an-ip',
				'::1',
				123,
			] )
		);
	}

	public function testSqliteLiteralFromIpv4UsesHexLiteral() :void {
		SqlBackend::setSqliteOverrideForTests( true );

		$this->assertSame(
			"X'7f000001'",
			IpAddressSql::literalFromIp( '127.0.0.1' )
		);
	}

	public function testSqliteLiteralFromIpv6UsesHexLiteral() :void {
		SqlBackend::setSqliteOverrideForTests( true );

		$this->assertSame(
			"X'00000000000000000000000000000001'",
			IpAddressSql::literalFromIp( '::1' )
		);
	}

	public function testSqliteLiteralFromInvalidIpReturnsNull() :void {
		SqlBackend::setSqliteOverrideForTests( true );

		$this->assertSame( 'NULL', IpAddressSql::literalFromIp( 'not-an-ip' ) );
	}

	public function testSqliteLiteralReturnsNullWhenInetPtonUnavailable() :void {
		SqlBackend::setSqliteOverrideForTests( true );
		IpAddressSql::setInetPtonAvailableOverrideForTests( false );

		$this->assertSame( 'NULL', IpAddressSql::literalFromIp( '127.0.0.1' ) );
	}

	public function testSqliteLiteralsFromIpsRetainsNullForInvalidInputs() :void {
		SqlBackend::setSqliteOverrideForTests( true );

		$this->assertSame(
			[
				"X'7f000001'",
				'NULL',
				"X'00000000000000000000000000000001'",
			],
			IpAddressSql::literalsFromIps( [
				'127.0.0.1',
				'not-an-ip',
				'::1',
				123,
			] )
		);
	}
}
