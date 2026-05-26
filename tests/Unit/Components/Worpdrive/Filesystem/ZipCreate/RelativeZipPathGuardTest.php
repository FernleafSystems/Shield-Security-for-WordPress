<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\Worpdrive\Filesystem\ZipCreate;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Filesystem\ZipCreate\RelativeZipPathGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class RelativeZipPathGuardTest extends BaseUnitTest {

	/**
	 * @dataProvider validPathsProvider
	 */
	public function test_valid_mapper_style_paths_are_accepted( string $path ) :void {
		$this->expectNotToPerformAssertions();

		( new RelativeZipPathGuard() )->assertValid( $path );
	}

	public function validPathsProvider() :array {
		return [
			'root file'   => [ 'index.php' ],
			'nested file' => [ 'wp-content/plugins/shield/file.php' ],
			'wp config'   => [ 'wp-config.php' ],
		];
	}

	/**
	 * @dataProvider invalidPathsProvider
	 */
	public function test_invalid_paths_are_rejected_before_archive_creation( string $path ) :void {
		$this->expectException( \InvalidArgumentException::class );

		( new RelativeZipPathGuard() )->assertValid( $path );
	}

	public function invalidPathsProvider() :array {
		return [
			'empty'              => [ '' ],
			'nul'                => [ "wp-content/file.php\0.txt" ],
			'absolute unix'      => [ '/wp-content/file.php' ],
			'absolute windows'   => [ '\\wp-content\\file.php' ],
			'drive path'         => [ 'C:\\site\\wp-content\\file.php' ],
			'parent traversal'   => [ '../wp-config.php' ],
			'nested traversal'   => [ 'wp-content/../wp-config.php' ],
			'current segment'    => [ './wp-content/file.php' ],
			'backslash segment'  => [ 'wp-content\\..\\wp-config.php' ],
		];
	}
}
