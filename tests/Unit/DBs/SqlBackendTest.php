<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\DBs;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Common\SqlBackend;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Services\Core\Db;
use FernleafSystems\Wordpress\Services\Services;

class SqliteWpdbDouble {
}

class SqlBackendTest extends BaseUnitTest {

	private $origServiceItems;

	private $origServices;

	private $origWpdb;

	protected function setUp() :void {
		parent::setUp();
		$this->origServiceItems = $this->getServicesProperty( 'items' )->getValue();
		$this->origServices = $this->getServicesProperty( 'services' )->getValue();
		global $wpdb;
		$this->origWpdb = $wpdb ?? null;
	}

	protected function tearDown() :void {
		SqlBackend::resetForTests();
		$this->getServicesProperty( 'items' )->setValue( null, $this->origServiceItems );
		$this->getServicesProperty( 'services' )->setValue( null, $this->origServices );
		global $wpdb;
		$wpdb = $this->origWpdb;
		parent::tearDown();
	}

	public function testDetectsSqliteFromMysqlInfo() :void {
		$this->injectWpDbService( $this->createDbWithMysqlInfo( 'SQLite 3.45.1' ) );

		$this->assertTrue( SqlBackend::isSqlite() );
	}

	public function testDetectsSqliteFromWpdbClassName() :void {
		$this->injectWpDbService( $this->createDbWithMysqlInfo( '8.0.36' ) );
		global $wpdb;
		$wpdb = new SqliteWpdbDouble();
		SqlBackend::resetForTests();

		$this->assertTrue( SqlBackend::isSqlite() );
	}

	public function testOverrideHasPriorityOverResolvedBackend() :void {
		$this->injectWpDbService( $this->createDbWithMysqlInfo( '8.0.36' ) );
		SqlBackend::setSqliteOverrideForTests( true );

		$this->assertTrue( SqlBackend::isSqlite() );
	}

	private function createDbWithMysqlInfo( string $mysqlInfo ) :Db {
		return new class( $mysqlInfo ) extends Db {
			private string $mysqlInfo;

			public function __construct( string $mysqlInfo ) {
				$this->mysqlInfo = $mysqlInfo;
			}

			public function getMysqlServerInfo() :string {
				return $this->mysqlInfo;
			}
		};
	}

	private function injectWpDbService( Db $db ) :void {
		$this->getServicesProperty( 'items' )->setValue( null, [
			'service_wpdb' => $db,
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
