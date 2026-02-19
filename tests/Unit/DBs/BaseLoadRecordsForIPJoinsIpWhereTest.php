<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\DBs;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\TableSchema;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Common\{
	BaseLoadRecordsForIPJoins,
	IpAddressSql,
	SqlBackend
};
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class BaseLoadRecordsForIPJoinsIpWhereTest extends TestCase {

	protected function tearDown() :void {
		IpAddressSql::resetForTests();
		SqlBackend::resetForTests();
		parent::tearDown();
	}

	public function testBuildWheresWithIpv4InMysqlMode() :void {
		SqlBackend::setSqliteOverrideForTests( false );
		$loader = $this->createLoader();
		$loader->setIP( '127.0.0.1' );

		$this->assertSame(
			[ "`ips`.`ip`=INET6_ATON('127.0.0.1')" ],
			$loader->exposeBuildWheres()
		);
	}

	public function testBuildWheresWithIpv6InSqliteMode() :void {
		SqlBackend::setSqliteOverrideForTests( true );
		$loader = $this->createLoader();
		$loader->setIP( '::1' );

		$this->assertSame(
			[ "`ips`.`ip`=X'00000000000000000000000000000001'" ],
			$loader->exposeBuildWheres()
		);
	}

	public function testBuildWheresWithInvalidIpUsesNullInSqliteMode() :void {
		SqlBackend::setSqliteOverrideForTests( true );
		$loader = $this->createLoader();
		$loader->setIP( 'not-an-ip' );

		$this->assertSame(
			[ '`ips`.`ip`=NULL' ],
			$loader->exposeBuildWheres()
		);
	}

	public function testBuildWheresWithEmptyIpAddsNoClause() :void {
		$loader = $this->createLoader();
		$loader->setIP( '' );

		$this->assertSame( [], $loader->exposeBuildWheres() );
	}

	private function createLoader() :object {
		return new class extends BaseLoadRecordsForIPJoins {

			public function select() :array {
				return [];
			}

			public function exposeBuildWheres() :array {
				return $this->buildWheres();
			}

			protected function getTableSchemaForJoinedTable() :TableSchema {
				$schema = new TableSchema();
				$schema->table = 'dummy_join';
				return $schema;
			}
		};
	}
}
