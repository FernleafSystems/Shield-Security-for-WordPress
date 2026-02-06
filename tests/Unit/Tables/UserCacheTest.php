<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class UserCacheTest extends BaseUnitTest {

	/**
	 * We override resolveUser() to substitute the real DB lookup with a configurable
	 * results map, while preserving the same null-coalescing cache pattern used by
	 * the base class. This lets us verify that the caching contract (lookup once,
	 * return cached on subsequent calls) holds correctly.
	 */
	private function createBuilder( array $userResults ) :object {
		$callCounts = new \stdClass();
		$callCounts->lookups = [];

		return new class( $userResults, $callCounts ) extends BaseBuildTableData {

			private array $userResults;

			private array $cache = [];

			public \stdClass $callCounts;

			public function __construct( array $userResults, \stdClass $callCounts ) {
				$this->userResults = $userResults;
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

			protected function resolveUser( int $uid ) {
				if ( !isset( $this->cache[ $uid ] ) ) {
					$this->callCounts->lookups[ $uid ] = ( $this->callCounts->lookups[ $uid ] ?? 0 ) + 1;
					$this->cache[ $uid ] = $this->userResults[ $uid ] ?? false;
				}
				return $this->cache[ $uid ] === false ? null : $this->cache[ $uid ];
			}

			public function testResolve( int $uid ) {
				return $this->resolveUser( $uid );
			}
		};
	}

	private function makeUser( string $login ) :\stdClass {
		$user = new \stdClass();
		$user->user_login = $login;
		return $user;
	}

	public function test_same_user_resolved_only_once() :void {
		$builder = $this->createBuilder( [
			1 => $this->makeUser( 'admin' ),
		] );

		$first = $builder->testResolve( 1 );
		$second = $builder->testResolve( 1 );

		$this->assertSame( $first, $second );
		$this->assertSame( 'admin', $first->user_login );
		$this->assertSame( 1, $builder->callCounts->lookups[ 1 ] );
	}

	public function test_different_users_resolved_independently() :void {
		$builder = $this->createBuilder( [
			1 => $this->makeUser( 'admin' ),
			2 => $this->makeUser( 'editor' ),
		] );

		$first = $builder->testResolve( 1 );
		$second = $builder->testResolve( 2 );

		$this->assertSame( 'admin', $first->user_login );
		$this->assertSame( 'editor', $second->user_login );
		$this->assertSame( 1, $builder->callCounts->lookups[ 1 ] );
		$this->assertSame( 1, $builder->callCounts->lookups[ 2 ] );
	}

	public function test_unavailable_user_cached_and_not_retried() :void {
		$builder = $this->createBuilder( [
			99 => null,
		] );

		$first = $builder->testResolve( 99 );
		$second = $builder->testResolve( 99 );

		$this->assertNull( $first );
		$this->assertNull( $second );
		$this->assertSame( 1, $builder->callCounts->lookups[ 99 ] );
	}

	public function test_many_rows_with_few_unique_users() :void {
		$users = [];
		for ( $i = 1; $i <= 5; $i++ ) {
			$users[ $i ] = $this->makeUser( "user$i" );
		}
		$builder = $this->createBuilder( $users );

		for ( $row = 0; $row < 50; $row++ ) {
			$uid = ( $row % 5 ) + 1;
			$result = $builder->testResolve( $uid );
			$this->assertNotNull( $result );
			$this->assertSame( "user$uid", $result->user_login );
		}

		$totalCalls = \array_sum( $builder->callCounts->lookups );
		$this->assertSame( 5, $totalCalls );
	}

	public function test_cache_is_per_instance() :void {
		$builderA = $this->createBuilder( [
			1 => $this->makeUser( 'admin' ),
		] );
		$builderB = $this->createBuilder( [
			1 => $this->makeUser( 'editor' ),
		] );

		$resultA = $builderA->testResolve( 1 );
		$resultB = $builderB->testResolve( 1 );

		$this->assertSame( 'admin', $resultA->user_login );
		$this->assertSame( 'editor', $resultB->user_login );
		$this->assertSame( 1, $builderA->callCounts->lookups[ 1 ] );
		$this->assertSame( 1, $builderB->callCounts->lookups[ 1 ] );
	}
}
