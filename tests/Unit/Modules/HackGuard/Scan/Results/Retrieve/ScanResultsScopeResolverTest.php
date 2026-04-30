<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Results\Retrieve;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\ScanResultsScopeResolver;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ScanResultsScopeResolverTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'esc_sql' )->alias( static fn( string $value ) :string => $value );
	}

	public function test_canonical_action_data_for_subject_normalizes_core_and_asset_subjects() :void {
		$resolver = new ScanResultsScopeResolver();

		$this->assertSame(
			[
				'type' => 'wordpress',
				'file' => 'wordpress',
			],
			$resolver->canonicalActionDataForSubject( 'core', 'core' )
		);
		$this->assertSame(
			[
				'type' => 'plugin',
				'file' => 'akismet/akismet.php',
			],
			$resolver->canonicalActionDataForSubject( 'plugin', 'akismet/akismet.php' )
		);
		$this->assertSame(
			[
				'type' => 'wordpress',
				'file' => 'wordpress',
			],
			$resolver->canonicalActionDataForSubject( 'wordpress', 'ignored' )
		);
	}

	public function test_wheres_for_action_scope_uses_shared_investigation_subject_queries() :void {
		$resolver = new ScanResultsScopeResolver();

		$this->assertSame(
			[
				"rim.`meta_key`='ptg_slug'",
				"rim.`meta_value`='hello-dolly/hello.php'",
			],
			$resolver->wheresForActionScope( 'plugin', 'hello-dolly/hello.php', 'rim' )
		);
		$this->assertSame(
			[
				"rim.`meta_key`='is_in_core'",
				"rim.`meta_value`=1",
			],
			$resolver->wheresForActionScope( 'wordpress', 'wordpress', 'rim' )
		);
		$this->assertSame(
			[
				"rim.`meta_key`='is_mal'",
				"rim.`meta_value`=1",
			],
			$resolver->wheresForActionScope( 'malware', 'malware', 'rim' )
		);
	}

	public function test_unsupported_scope_type_is_rejected() :void {
		$this->expectException( \InvalidArgumentException::class );

		( new ScanResultsScopeResolver() )->canonicalActionDataForSubject( 'unknown', 'anything' );
	}

	public function test_unsupported_where_scope_does_not_fall_back_to_core() :void {
		$this->expectException( \InvalidArgumentException::class );

		( new ScanResultsScopeResolver() )->wheresForActionScope( 'unknown', 'anything', 'rim' );
	}

	public function test_empty_asset_scope_is_rejected() :void {
		$this->expectException( \InvalidArgumentException::class );

		( new ScanResultsScopeResolver() )->canonicalActionDataForSubject( 'plugin', '' );
	}
}
