<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalWpTestsConfigGuard;

class RecordingLocalWpTestsConfigGuard extends LocalWpTestsConfigGuard {

	/**
	 * @var array<int,array{
	 *     wp_tests_dir:string,
	 *     expected:array{DB_NAME:string,DB_USER:string,DB_PASSWORD:string,DB_HOST:string}
	 * }>
	 */
	public array $removeIfStaleCalls = [];

	/**
	 * @var array<int,array{
	 *     wp_tests_dir:string,
	 *     expected:array{DB_NAME:string,DB_USER:string,DB_PASSWORD:string,DB_HOST:string}
	 * }>
	 */
	public array $assertMatchesCalls = [];

	/**
	 * @param array{DB_NAME:string,DB_USER:string,DB_PASSWORD:string,DB_HOST:string} $expected
	 */
	public function removeIfStale( string $wpTestsDir, array $expected ) :void {
		$this->removeIfStaleCalls[] = [
			'wp_tests_dir' => $wpTestsDir,
			'expected' => $expected,
		];
	}

	/**
	 * @param array{DB_NAME:string,DB_USER:string,DB_PASSWORD:string,DB_HOST:string} $expected
	 */
	public function assertMatches( string $wpTestsDir, array $expected ) :void {
		$this->assertMatchesCalls[] = [
			'wp_tests_dir' => $wpTestsDir,
			'expected' => $expected,
		];
	}
}
