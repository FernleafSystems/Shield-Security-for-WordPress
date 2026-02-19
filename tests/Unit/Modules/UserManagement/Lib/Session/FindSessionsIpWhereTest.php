<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\UserManagement\Lib\Session;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Common\{
	IpAddressSql,
	SqlBackend
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\FindSessions;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class FindSessionsIpWhereTest extends TestCase {

	protected function tearDown() :void {
		IpAddressSql::resetForTests();
		SqlBackend::resetForTests();
		parent::tearDown();
	}

	public function testPrivateIpWhereUsesMysqlNativeExpression() :void {
		SqlBackend::setSqliteOverrideForTests( false );
		$finder = new FindSessions();
		$method = new \ReflectionMethod( $finder, 'getWhere_IPEquals' );
		$method->setAccessible( true );

		$this->assertSame(
			"`ips`.`ip`=INET6_ATON('127.0.0.1')",
			$method->invoke( $finder, '127.0.0.1' )
		);
	}

	public function testPrivateIpWhereUsesBinaryLiteralForSqlite() :void {
		SqlBackend::setSqliteOverrideForTests( true );
		$finder = new FindSessions();
		$method = new \ReflectionMethod( $finder, 'getWhere_IPEquals' );
		$method->setAccessible( true );

		$this->assertSame(
			"`ips`.`ip`=X'00000000000000000000000000000001'",
			$method->invoke( $finder, '::1' )
		);
	}

	public function testPrivateIpWhereReturnsNullComparisonForInvalidSqliteIp() :void {
		SqlBackend::setSqliteOverrideForTests( true );
		$finder = new FindSessions();
		$method = new \ReflectionMethod( $finder, 'getWhere_IPEquals' );
		$method->setAccessible( true );

		$this->assertSame(
			'`ips`.`ip`=NULL',
			$method->invoke( $finder, 'invalid-ip' )
		);
	}
}
