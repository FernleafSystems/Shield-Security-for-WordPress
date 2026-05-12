<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\DBs;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\TableSchema;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Common\{
	BaseLoadRecordsForIPJoins,
	IpAddressSql,
	SqlBackend
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpMeta\LoadIpMeta;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\LoadIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\LoadRequestLogs;
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

	public function testBuildOrderByAcceptsSchemaColumnAndUsesCanonicalName() :void {
		$loader = $this->createLoader( [ 'safe_col' ], 'fallback_col' );
		$loader->order_by = 'SAFE_COL';
		$loader->order_dir = 'ASC';

		$this->assertSame( 'ORDER BY `joined_table`.`safe_col` ASC', $loader->exposeBuildOrderBy() );
	}

	public function testBuildOrderByFallsBackForMaliciousColumn() :void {
		$loader = $this->createLoader( [ 'fallback_col', 'safe_col' ], 'fallback_col' );
		$loader->order_by = "safe_col` DESC, (SELECT SLEEP(1)) -- ";
		$loader->order_dir = 'ASC';

		$orderBy = $loader->exposeBuildOrderBy();

		$this->assertSame( 'ORDER BY `joined_table`.`fallback_col` ASC', $orderBy );
		$this->assertStringNotContainsString( 'SLEEP', $orderBy );
		$this->assertStringNotContainsString( '--', $orderBy );
	}

	public function testBuildOrderByNormalisesInvalidDirectionToDesc() :void {
		$loader = $this->createLoader( [ 'safe_col' ] );
		$loader->order_by = 'safe_col';
		$loader->order_dir = 'sideways';

		$this->assertSame( 'ORDER BY `joined_table`.`safe_col` DESC', $loader->exposeBuildOrderBy() );
	}

	public function testBuildOrderByKeepsEmptyOrderByEmpty() :void {
		$loader = $this->createLoader( [ 'safe_col' ], 'safe_col' );
		$loader->order_by = '';
		$loader->order_dir = 'ASC';

		$this->assertSame( '', $loader->exposeBuildOrderBy() );
	}

	public function testBuildOrderByOmitsOrderingWhenFallbackIsNotInSchema() :void {
		$loader = $this->createLoader( [ 'safe_col' ], 'missing_col' );
		$loader->order_by = 'unsafe_col';
		$loader->order_dir = 'ASC';

		$this->assertSame( '', $loader->exposeBuildOrderBy() );
	}

	public function testConcreteIpJoinLoadersDeclareSpecificFallbackColumns() :void {
		$this->assertSame( 'created_at', ( new class extends LoadRequestLogs {
			public function exposeFallbackOrderByColumn() :string {
				return $this->getFallbackOrderByColumn();
			}
		} )->exposeFallbackOrderByColumn() );
		$this->assertSame( 'last_access_at', ( new class extends LoadIpRules {
			public function exposeFallbackOrderByColumn() :string {
				return $this->getFallbackOrderByColumn();
			}
		} )->exposeFallbackOrderByColumn() );
		$this->assertSame( 'updated_at', ( new class extends LoadIpMeta {
			public function exposeFallbackOrderByColumn() :string {
				return $this->getFallbackOrderByColumn();
			}
		} )->exposeFallbackOrderByColumn() );
	}

	private function createLoader( array $columns = [], string $fallback = 'id' ) :object {
		$schema = $this->createSchema( $columns );
		return new class( $schema, $fallback ) extends BaseLoadRecordsForIPJoins {

			private TableSchema $schema;

			private string $fallback;

			public function __construct( TableSchema $schema, string $fallback ) {
				$this->schema = $schema;
				$this->fallback = $fallback;
			}

			public function select() :array {
				return [];
			}

			public function exposeBuildWheres() :array {
				return $this->buildWheres();
			}

			public function exposeBuildOrderBy() :string {
				return $this->buildOrderBy();
			}

			protected function getFallbackOrderByColumn() :string {
				return $this->fallback;
			}

			protected function getTableSchemaForJoinedTable() :TableSchema {
				return $this->schema;
			}
		};
	}

	private function createSchema( array $columns ) :TableSchema {
		$customColumns = [];
		foreach ( \array_diff( $columns, [ 'id', 'created_at', 'updated_at' ] ) as $column ) {
			$customColumns[ $column ] = [];
		}

		return ( new TableSchema() )->applyFromArray( [
			'slug'           => 'dummy_join',
			'cols_custom'    => $customColumns,
			'has_created_at' => \in_array( 'created_at', $columns, true ),
			'has_updated_at' => \in_array( 'updated_at', $columns, true ),
		] );
	}
}
