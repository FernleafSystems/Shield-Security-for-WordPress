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

	public function testByUserBuildsExpectedUserWhereClause() :void {
		$finder = new class extends FindSessions {
			public array $capturedWheres = [];

			public function lookupFromUserMeta( array $wheres = [], int $limit = 10, string $orderBy = '`user_meta`.`last_login_at`' ) :array {
				$this->capturedWheres = $wheres;
				return [];
			}
		};

		$finder->byUser( 21 );

		$this->assertSame(
			[ '`user_meta`.`user_id`=21' ],
			$finder->capturedWheres
		);
	}

	public function testByUserSkipsLookupForInvalidUserId() :void {
		$finder = new class extends FindSessions {
			public bool $lookupCalled = false;

			public function lookupFromUserMeta( array $wheres = [], int $limit = 10, string $orderBy = '`user_meta`.`last_login_at`' ) :array {
				$this->lookupCalled = true;
				return [];
			}
		};

		$finder->byUser( 0 );

		$this->assertFalse( $finder->lookupCalled );
	}
}
