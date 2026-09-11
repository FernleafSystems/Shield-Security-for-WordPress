<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\FileLocker;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Utility\FileLockerState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class FileLockerStateTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();

		if ( !\defined( 'ABSPATH' ) ) {
			\define( 'ABSPATH', '/tmp/shield-current-site/' );
		}

		Functions\when( 'wp_normalize_path' )->alias(
			static fn( string $path ) :string => \str_replace( '\\', '/', $path )
		);
		Functions\when( 'trailingslashit' )->alias(
			static fn( string $path ) :string => \rtrim( \str_replace( '\\', '/', $path ), '/' ).'/'
		);
	}

	public function test_build_returns_safe_default_state() :void {
		$state = ( new FileLockerState() )->build( [] );

		$this->assertSame( $this->normalizedAbsPath(), $state[ 'abspath' ] );
		$this->assertSame( 0, $state[ 'last_analysis_started_at' ] );
		$this->assertSame( 0, $state[ 'last_locks_created_at' ] );
		$this->assertSame( 0, $state[ 'last_locks_created_failed_at' ] );
		$this->assertSame( 0, $state[ 'cipher_last_checked_at' ] );
		$this->assertSame( '', $state[ 'last_error' ] );
		$this->assertSame( '', $state[ 'cipher' ] );
	}

	public function test_build_normalizes_corrupt_nested_values() :void {
		$state = ( new FileLockerState() )->build( [
			'abspath'                      => false,
			'last_analysis_started_at'     => '17',
			'last_locks_created_at'        => [],
			'last_locks_created_failed_at' => 'not numeric',
			'cipher_last_checked_at'       => 23.8,
			'last_error'                   => 123,
			'cipher'                       => false,
			'unknown'                      => [ 'kept' ],
		] );

		$this->assertSame( $this->normalizedAbsPath(), $state[ 'abspath' ] );
		$this->assertSame( 17, $state[ 'last_analysis_started_at' ] );
		$this->assertSame( 0, $state[ 'last_locks_created_at' ] );
		$this->assertSame( 0, $state[ 'last_locks_created_failed_at' ] );
		$this->assertSame( 23, $state[ 'cipher_last_checked_at' ] );
		$this->assertSame( '', $state[ 'last_error' ] );
		$this->assertSame( '', $state[ 'cipher' ] );
		$this->assertArrayNotHasKey( 'unknown', $state );
	}

	public function test_prepare_for_storage_normalizes_only_supplied_known_fields() :void {
		$state = ( new FileLockerState() )->prepareForStorage( [
			'abspath'                  => 'C:\\hosting\\site\\public',
			'last_analysis_started_at' => '12',
			'last_error'               => [],
			'unknown'                  => 'kept',
		] );

		$this->assertSame( 'C:/hosting/site/public/', $state[ 'abspath' ] );
		$this->assertSame( 12, $state[ 'last_analysis_started_at' ] );
		$this->assertSame( '', $state[ 'last_error' ] );
		$this->assertArrayNotHasKey( 'unknown', $state );
		$this->assertArrayNotHasKey( 'cipher', $state );
		$this->assertArrayNotHasKey( 'last_locks_created_at', $state );
	}

	public function test_prepare_for_storage_keeps_empty_state_empty() :void {
		$this->assertSame( [], ( new FileLockerState() )->prepareForStorage( [] ) );
	}

	private function normalizedAbsPath() :string {
		return \trailingslashit( \wp_normalize_path( ABSPATH ) );
	}
}
