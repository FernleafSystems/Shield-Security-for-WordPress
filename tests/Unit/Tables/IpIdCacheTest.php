<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class IpIdCacheTest extends BaseUnitTest {

	private function createBuilder( array $ipResults ) :object {
		$callCounts = new \stdClass();
		$callCounts->counts = [];

		$ipIdMocks = [];
		foreach ( $ipResults as $ip => $result ) {
			if ( !( $result instanceof \Exception ) ) {
				$mock = $this->createMock( IpID::class );
				$mock->method( 'run' )->willReturn( $result );
				$ipIdMocks[ $ip ] = $mock;
			}
		}

		return new class( $ipResults, $ipIdMocks, $callCounts ) extends BaseBuildTableData {

			private array $ipResults;

			private array $ipIdMocks;

			public \stdClass $callCounts;

			public function __construct( array $ipResults, array $ipIdMocks, \stdClass $callCounts ) {
				$this->ipResults = $ipResults;
				$this->ipIdMocks = $ipIdMocks;
				$this->callCounts = $callCounts;
			}

			protected function countTotalRecords() :int {
				return 0;
			}

			protected function countTotalRecordsFiltered() :int {
				return 0;
			}

			protected function buildTableRowsFromRawRecords( array $records ) :array {
				return [];
			}

			protected function getSearchPanesDataBuilder() :\FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildSearchPanesData {
				throw new \RuntimeException( 'Not implemented' );
			}

			protected function createIpIdentifier( string $ip ) :IpID {
				if ( !isset( $this->callCounts->counts[ $ip ] ) ) {
					$this->callCounts->counts[ $ip ] = 0;
				}
				$this->callCounts->counts[ $ip ]++;

				if ( isset( $this->ipResults[ $ip ] ) ) {
					$result = $this->ipResults[ $ip ];
					if ( $result instanceof \Exception ) {
						throw $result;
					}
					return $this->ipIdMocks[ $ip ];
				}

				throw new \RuntimeException( 'Unexpected IP: '.$ip );
			}

			public function testResolve( string $ip ) :?array {
				return $this->resolveIpIdentity( $ip );
			}
		};
	}

	public function test_same_ip_resolved_only_once() :void {
		$builder = $this->createBuilder( [
			'1.2.3.4' => [ IpID::VISITOR, 'Visitor' ],
		] );

		$first = $builder->testResolve( '1.2.3.4' );
		$second = $builder->testResolve( '1.2.3.4' );

		$this->assertSame( $first, $second );
		$this->assertSame( 1, $builder->callCounts->counts[ '1.2.3.4' ] );
	}

	public function test_different_ips_resolved_independently() :void {
		$builder = $this->createBuilder( [
			'1.2.3.4' => [ IpID::VISITOR, 'Visitor' ],
			'5.6.7.8' => [ IpID::THIS_SERVER, 'Server' ],
		] );

		$first = $builder->testResolve( '1.2.3.4' );
		$second = $builder->testResolve( '5.6.7.8' );

		$this->assertSame( [ IpID::VISITOR, 'Visitor' ], $first );
		$this->assertSame( [ IpID::THIS_SERVER, 'Server' ], $second );
		$this->assertSame( 1, $builder->callCounts->counts[ '1.2.3.4' ] );
		$this->assertSame( 1, $builder->callCounts->counts[ '5.6.7.8' ] );
	}

	public function test_exception_cached_and_not_retried() :void {
		$builder = $this->createBuilder( [
			'1.2.3.4' => new \RuntimeException( 'DNS failure' ),
		] );

		$first = $builder->testResolve( '1.2.3.4' );
		$second = $builder->testResolve( '1.2.3.4' );

		$this->assertNull( $first );
		$this->assertNull( $second );
		$this->assertSame( 1, $builder->callCounts->counts[ '1.2.3.4' ] );
	}

	public function test_many_rows_with_few_unique_ips() :void {
		$ips = [];
		for ( $i = 1; $i <= 5; $i++ ) {
			$ips[ "10.0.0.$i" ] = [ IpID::UNKNOWN, "IP $i" ];
		}
		$builder = $this->createBuilder( $ips );

		for ( $row = 0; $row < 50; $row++ ) {
			$ip = '10.0.0.'.( ( $row % 5 ) + 1 );
			$result = $builder->testResolve( $ip );
			$this->assertIsArray( $result );
		}

		$totalCalls = \array_sum( $builder->callCounts->counts );
		$this->assertSame( 5, $totalCalls );
	}

	public function test_cache_is_per_instance() :void {
		$builderA = $this->createBuilder( [
			'1.2.3.4' => [ IpID::VISITOR, 'Visitor' ],
		] );
		$builderB = $this->createBuilder( [
			'1.2.3.4' => [ IpID::THIS_SERVER, 'Server' ],
		] );

		$resultA = $builderA->testResolve( '1.2.3.4' );
		$resultB = $builderB->testResolve( '1.2.3.4' );

		$this->assertSame( [ IpID::VISITOR, 'Visitor' ], $resultA );
		$this->assertSame( [ IpID::THIS_SERVER, 'Server' ], $resultB );
		$this->assertSame( 1, $builderA->callCounts->counts[ '1.2.3.4' ] );
		$this->assertSame( 1, $builderB->callCounts->counts[ '1.2.3.4' ] );
	}
}
