<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\DBs;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Common\{
	IpAddressSql,
	SqlBackend
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\Ops\Insert;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Services\Core\Db;
use FernleafSystems\Wordpress\Services\Core\Request;
use FernleafSystems\Wordpress\Services\Services;

class IPsOpsInsertSqlTest extends BaseUnitTest {

	private $origServiceItems;

	private $origServices;

	protected function setUp() :void {
		parent::setUp();
		$this->origServiceItems = $this->getServicesProperty( 'items' )->getValue();
		$this->origServices = $this->getServicesProperty( 'services' )->getValue();
	}

	protected function tearDown() :void {
		IpAddressSql::resetForTests();
		SqlBackend::resetForTests();
		$this->getServicesProperty( 'items' )->setValue( null, $this->origServiceItems );
		$this->getServicesProperty( 'services' )->setValue( null, $this->origServices );
		parent::tearDown();
	}

	public function testInsertBuildsMysqlNativeIpv4Expression() :void {
		SqlBackend::setSqliteOverrideForTests( false );
		$db = $this->createCapturingDb();
		$this->injectServices( $db );

		$insert = $this->createInsertQuery();
		$insert->insert( (object)[ 'ip' => '127.0.0.1' ] );

		$this->assertSame(
			"INSERT IGNORE INTO `wp_icwp_wpsf_ips` (`ip`,`created_at`) VALUES (INET6_ATON('127.0.0.1'), 1771498280)",
			$db->lastSql
		);
	}

	public function testInsertBuildsMysqlNativeExpressionForInvalidIp() :void {
		SqlBackend::setSqliteOverrideForTests( false );
		$db = $this->createCapturingDb();
		$this->injectServices( $db );

		$insert = $this->createInsertQuery();
		$insert->insert( (object)[ 'ip' => 'not-an-ip' ] );

		$this->assertSame(
			"INSERT IGNORE INTO `wp_icwp_wpsf_ips` (`ip`,`created_at`) VALUES (INET6_ATON('not-an-ip'), 1771498280)",
			$db->lastSql
		);
	}

	public function testInsertBuildsSqliteHexLiteral() :void {
		SqlBackend::setSqliteOverrideForTests( true );
		$db = $this->createCapturingDb();
		$this->injectServices( $db );

		$insert = $this->createInsertQuery();
		$insert->insert( (object)[ 'ip' => '::1' ] );

		$this->assertSame(
			"INSERT IGNORE INTO `wp_icwp_wpsf_ips` (`ip`,`created_at`) VALUES (X'00000000000000000000000000000001', 1771498280)",
			$db->lastSql
		);
	}

	public function testInsertBuildsNullForSqliteInvalidIp() :void {
		SqlBackend::setSqliteOverrideForTests( true );
		$db = $this->createCapturingDb();
		$this->injectServices( $db );

		$insert = $this->createInsertQuery();
		$insert->insert( (object)[ 'ip' => 'not-an-ip' ] );

		$this->assertSame(
			"INSERT IGNORE INTO `wp_icwp_wpsf_ips` (`ip`,`created_at`) VALUES (NULL, 1771498280)",
			$db->lastSql
		);
	}

	private function createInsertQuery() :Insert {
		$insert = new Insert();
		$insert->setDbH( new class {
			public function getTableSchema() {
				return (object)[
					'table' => 'wp_icwp_wpsf_ips',
				];
			}
		} );
		return $insert;
	}

	private function createCapturingDb() :Db {
		return new class extends Db {
			public string $lastSql = '';

			public function doSql( string $sqlQuery ) {
				$this->lastSql = $sqlQuery;
				return 1;
			}
		};
	}

	private function injectServices( Db $db ) :void {
		$this->getServicesProperty( 'items' )->setValue( null, [
			'service_wpdb'    => $db,
			'service_request' => new class extends Request {
				public function __construct() {
				}

				public function ts( bool $update = true ) :int {
					return 1771498280;
				}
			},
		] );
		$this->getServicesProperty( 'services' )->setValue( null, null );
	}

	private function getServicesProperty( string $propertyName ) :\ReflectionProperty {
		$reflection = new \ReflectionClass( Services::class );
		$property = $reflection->getProperty( $propertyName );
		$property->setAccessible( true );
		return $property;
	}
}
