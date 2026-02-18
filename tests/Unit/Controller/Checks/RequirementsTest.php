<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Controller\Checks;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Checks\Requirements;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Services\Core\Db;
use FernleafSystems\Wordpress\Services\Services;

class RequirementsTest extends BaseUnitTest {

	private $origServiceItems;

	private $origServices;

	protected function setUp() :void {
		parent::setUp();
		$this->origServiceItems = $this->getServicesProperty( 'items' )->getValue();
		$this->origServices = $this->getServicesProperty( 'services' )->getValue();
	}

	protected function tearDown() :void {
		$this->getServicesProperty( 'items' )->setValue( null, $this->origServiceItems );
		$this->getServicesProperty( 'services' )->setValue( null, $this->origServices );
		parent::tearDown();
	}

	public function testSqliteBackendShortCircuitsAsSupported() :void {
		$db = $this->createMockDb( 'SQLite 3.45.1' );
		$this->injectWpDbService( $db );

		$supported = ( new Requirements() )->isMysqlVersionSupported( '5.6' );

		$this->assertTrue( $supported );
		$this->assertSame( 0, $db->selectCustomCalls );
	}

	public function testOldMysqlUsesMiscFunctionProbe() :void {
		$db = $this->createMockDb( '5.5.4', [
			[ 'name' => 'INET6_ATON' ],
		] );
		$this->injectWpDbService( $db );

		$supported = ( new Requirements() )->isMysqlVersionSupported( '5.6' );

		$this->assertTrue( $supported );
		$this->assertSame( 1, $db->selectCustomCalls );
	}

	public function testOldMysqlWithoutProbeSupportRemainsUnsupported() :void {
		$db = $this->createMockDb( '5.5.4', [
			[ 'name' => 'SOME_OTHER_FUNCTION' ],
		] );
		$this->injectWpDbService( $db );

		$supported = ( new Requirements() )->isMysqlVersionSupported( '5.6' );

		$this->assertFalse( $supported );
		$this->assertSame( 1, $db->selectCustomCalls );
	}

	public function testProbeFailuresDoNotThrowAndReturnUnsupported() :void {
		$db = $this->createMockDb( '5.5.4', [], true );
		$this->injectWpDbService( $db );

		$supported = ( new Requirements() )->isMysqlVersionSupported( '5.6' );

		$this->assertFalse( $supported );
		$this->assertSame( 1, $db->selectCustomCalls );
	}

	private function createMockDb( string $mysqlInfo, array $probeResponse = [], bool $throwOnProbe = false ) :Db {
		return new class( $mysqlInfo, $probeResponse, $throwOnProbe ) extends Db {

			public int $selectCustomCalls = 0;

			private string $mysqlInfo;

			private array $probeResponse;

			private bool $throwOnProbe;

			public function __construct( string $mysqlInfo, array $probeResponse, bool $throwOnProbe ) {
				$this->mysqlInfo = $mysqlInfo;
				$this->probeResponse = $probeResponse;
				$this->throwOnProbe = $throwOnProbe;
			}

			public function getMysqlServerInfo() :string {
				return $this->mysqlInfo;
			}

			public function selectCustom( $query, $format = null ) {
				$this->selectCustomCalls++;
				if ( $this->throwOnProbe ) {
					throw new \RuntimeException( 'Probe query failed' );
				}
				return $this->probeResponse;
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
