<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\DBs;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Common\{
	IpAddressSql,
	SqlBackend
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\Ops\Common;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\IpUtils;

class IPsOpsCommonTest extends BaseUnitTest {

	private $origServiceItems;

	private $origServices;

	protected function setUp() :void {
		parent::setUp();
		$this->origServiceItems = $this->getServicesProperty( 'items' )->getValue();
		$this->origServices = $this->getServicesProperty( 'services' )->getValue();
		$this->injectIpService( new class extends IpUtils {
			public function isValidIp( $ip, $flags = null ) {
				return \filter_var( \trim( (string)$ip ), \FILTER_VALIDATE_IP, empty( $flags ) ? 0 : $flags ) !== false;
			}
		} );
	}

	protected function tearDown() :void {
		IpAddressSql::resetForTests();
		SqlBackend::resetForTests();
		$this->getServicesProperty( 'items' )->setValue( null, $this->origServiceItems );
		$this->getServicesProperty( 'services' )->setValue( null, $this->origServices );
		parent::tearDown();
	}

	public function testFilterByIPHumanWithIpv4UsesMysqlExpressionInMysqlMode() :void {
		SqlBackend::setSqliteOverrideForTests( false );
		$query = $this->createQueryObject();

		$query->filterByIPHuman( '127.0.0.1' );

		$this->assertSame( [ [ '`ip`', '=', "INET6_ATON('127.0.0.1')" ] ], $query->wheres );
	}

	public function testFilterByIPHumanWithIpv4UsesHexLiteralInSqliteMode() :void {
		SqlBackend::setSqliteOverrideForTests( true );
		$query = $this->createQueryObject();

		$query->filterByIPHuman( '127.0.0.1' );

		$this->assertSame( [ [ '`ip`', '=', "X'7f000001'" ] ], $query->wheres );
	}

	public function testFilterByIPHumanWithPackedIpv6UsesHexLiteralInSqliteMode() :void {
		SqlBackend::setSqliteOverrideForTests( true );
		$query = $this->createQueryObject();

		$query->filterByIPHuman( (string)\inet_pton( '::1' ) );

		$this->assertSame( [ [ '`ip`', '=', "X'00000000000000000000000000000001'" ] ], $query->wheres );
	}

	public function testFilterByIPHumanWithEmptyIpPreservesLegacyEmptyStringWhere() :void {
		$query = $this->createQueryObject();

		$query->filterByIPHuman( '' );

		$this->assertSame( [ [ '`ip`', '=', "''" ] ], $query->wheres );
	}

	public function testFilterByIPHumanWithInvalidIpAddsNoWhere() :void {
		$query = $this->createQueryObject();

		$query->filterByIPHuman( 'not-an-ip' );

		$this->assertSame( [], $query->wheres );
	}

	public function testFilterByIPHumanWithInvalidPackedLengthAddsNoWhere() :void {
		$query = $this->createQueryObject();

		$query->filterByIPHuman( 'abc' );

		$this->assertSame( [], $query->wheres );
	}

	public function testBackendSwitchSmokeOnSameConsumerAndInput() :void {
		$query = $this->createQueryObject();

		SqlBackend::setSqliteOverrideForTests( false );
		$query->filterByIPHuman( '127.0.0.1' );
		$mysqlWhere = $query->wheres[ 0 ][ 2 ] ?? '';

		$query->wheres = [];
		SqlBackend::setSqliteOverrideForTests( true );
		$query->filterByIPHuman( '127.0.0.1' );
		$sqliteWhere = $query->wheres[ 0 ][ 2 ] ?? '';

		$this->assertSame( "INET6_ATON('127.0.0.1')", $mysqlWhere );
		$this->assertSame( "X'7f000001'", $sqliteWhere );
		$this->assertNotSame( $mysqlWhere, $sqliteWhere );
	}

	private function createQueryObject() :object {
		return new class {

			use Common;

			public array $wheres = [];

			public function addRawWhere( array $where ) :self {
				$this->wheres[] = $where;
				return $this;
			}
		};
	}

	private function injectIpService( IpUtils $ip ) :void {
		$this->getServicesProperty( 'items' )->setValue( null, [
			'service_ip' => $ip,
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
