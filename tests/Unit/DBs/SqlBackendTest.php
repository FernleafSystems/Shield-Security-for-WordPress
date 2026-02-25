<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\DBs;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Common\SqlBackend;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Services\Core\Db;
use FernleafSystems\Wordpress\Services\Services;

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

	public function testDetectsSqliteFromKnownWpSqliteWpdbClassName() :void {
		$this->injectWpDbService( $this->createDbWithMysqlInfo( '8.0.36' ) );

		$wpdbClass = $this->ensureGlobalClass( 'WP_SQLite_Wpdb_Alias' );
		global $wpdb;
		$wpdb = new $wpdbClass();
		SqlBackend::resetForTests();

		$this->assertTrue( SqlBackend::isSqlite() );
	}

	public function testDetectsSqliteFromWpdbParentClass() :void {
		$this->injectWpDbService( $this->createDbWithMysqlInfo( '8.0.36' ) );

		if ( !\class_exists( '\WP_SQLite_DB', false ) ) {
			$this->ensureGlobalClass( 'WP_SQLite_DB' );
		}

		global $wpdb;
		$wpdb = ( new \ReflectionClass( '\WP_SQLite_DB' ) )->newInstanceWithoutConstructor();
		SqlBackend::resetForTests();

		$this->assertTrue( SqlBackend::isSqlite() );
	}

	public function testDetectsSqliteFromKnownWpSqliteDbhClassName() :void {
		$this->injectWpDbService( $this->createDbWithMysqlInfo( '8.0.36' ) );

		$dbhClass = $this->ensureGlobalClass( 'WP_SQLite_Translator_Alias' );
		global $wpdb;
		$wpdb = (object)[
			'dbh' => new $dbhClass(),
		];
		SqlBackend::resetForTests();

		$this->assertTrue( SqlBackend::isSqlite() );
	}

	public function testDoesNotDetectSqliteFromNormalWpdbAndDbhClasses() :void {
		$this->injectWpDbService( $this->createDbWithMysqlInfo( '8.0.36' ) );
		global $wpdb;
		$wpdb = (object)[
			'dbh' => new class {
			},
		];
		SqlBackend::resetForTests();

		$this->assertFalse( SqlBackend::isSqlite() );
	}

	public function testDoesNotDetectSqliteFromArbitraryWpdbClassContainingSqlite() :void {
		$this->injectWpDbService( $this->createDbWithMysqlInfo( '8.0.36' ) );

		$wpdbClass = $this->ensureGlobalClass( 'AcmeSqliteWpdbAlias' );
		global $wpdb;
		$wpdb = new $wpdbClass();
		SqlBackend::resetForTests();

		$this->assertFalse( SqlBackend::isSqlite() );
	}

	public function testDoesNotDetectSqliteFromArbitraryDbhClassContainingSqlite() :void {
		$this->injectWpDbService( $this->createDbWithMysqlInfo( '8.0.36' ) );

		$dbhClass = $this->ensureGlobalClass( 'AcmeSqliteDbhAlias' );
		global $wpdb;
		$wpdb = (object)[
			'dbh' => new $dbhClass(),
		];
		SqlBackend::resetForTests();

		$this->assertFalse( SqlBackend::isSqlite() );
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

	private function ensureGlobalClass( string $className ) :string {
		$fqn = '\\'.$className;
		if ( !\class_exists( $fqn, false ) ) {
			eval( \sprintf( 'namespace { class %s {} }', $className ) );
		}
		return $fqn;
	}
}
