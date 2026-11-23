<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\SiteQuery;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\SiteQueryCache;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class SiteQueryCacheTest extends BaseUnitTest {

	private const TRANSIENT_KEY = 'shield_security_site_query_cache';

	/**
	 * @var array<string,mixed>
	 */
	private array $transients = [];

	private int $transientReads = 0;

	protected function setUp() :void {
		parent::setUp();
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->cfg = (object)[
			'properties' => [
				'slug_parent' => 'shield',
				'slug_plugin' => 'security',
			],
		];
		PluginControllerInstaller::install( $controller );

		$this->transients = [];
		$this->transientReads = 0;
		Functions\when( 'wp_salt' )->justReturn( 'test-salt' );
		Functions\when( 'get_transient' )->alias( function ( string $key ) {
			$this->transientReads++;
			return $this->transients[ $key ] ?? false;
		} );
		Functions\when( 'set_transient' )->alias( function ( string $key, $value, int $ttl ) {
			$this->assertSame( \WEEK_IN_SECONDS, $ttl );
			$this->transients[ $key ] = $value;
			return true;
		} );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_value_is_worked_out_once_and_reused_by_later_requests() :void {
		$calls = 0;
		$producer = function () use ( &$calls ) :bool {
			$calls++;
			return true;
		};

		$this->assertTrue( ( new SiteQueryCache() )->remember( 'db_password', 'secret-one', $producer ) );
		$this->assertTrue( ( new SiteQueryCache() )->remember( 'db_password', 'secret-one', $producer ) );

		$this->assertSame( 1, $calls );
	}

	public function test_value_is_worked_out_again_when_its_source_changes() :void {
		( new SiteQueryCache() )->remember( 'db_password', 'secret-one', fn() => false );

		$this->assertTrue( ( new SiteQueryCache() )->remember( 'db_password', 'secret-two', fn() => true ) );
		$this->assertTrue( ( new SiteQueryCache() )->remember( 'db_password', 'secret-two', fn() => false ) );
	}

	public function test_a_stored_false_value_is_reused_rather_than_worked_out_again() :void {
		( new SiteQueryCache() )->remember( 'db_password', 'secret-one', fn() => false );

		$this->assertFalse( ( new SiteQueryCache() )->remember( 'db_password', 'secret-one', fn() => true ) );
	}

	public function test_source_is_never_stored() :void {
		( new SiteQueryCache() )->remember( 'db_password', 'secret-one', fn() => true );

		$this->assertStringNotContainsString( 'secret-one', \serialize( $this->transients[ self::TRANSIENT_KEY ] ) );
	}

	public function test_fingerprints_are_keyed_by_the_site_salt() :void {
		( new SiteQueryCache() )->remember( 'db_password', 'secret-one', fn() => false );

		Functions\when( 'wp_salt' )->justReturn( 'rotated-salt' );
		$this->assertTrue( ( new SiteQueryCache() )->remember( 'db_password', 'secret-one', fn() => true ) );
	}

	public function test_entries_share_one_transient_read_once_per_request() :void {
		$cache = new SiteQueryCache();
		$cache->remember( 'first', 'a', fn() => 1 );
		$cache->remember( 'second', 'b', fn() => 2 );
		$this->assertSame( 1, $this->transientReads );

		$later = new SiteQueryCache();
		$this->assertSame( 1, $later->remember( 'first', 'a', fn() => 99 ) );
		$this->assertSame( 2, $later->remember( 'second', 'b', fn() => 99 ) );
		$this->assertSame( 2, $this->transientReads );
	}

	public function test_malformed_stored_entries_are_worked_out_again() :void {
		$this->transients[ self::TRANSIENT_KEY ] = [
			'db_password' => [ 'value' => true ],
		];

		$this->assertFalse( ( new SiteQueryCache() )->remember( 'db_password', 'secret-one', fn() => false ) );
	}
}
