<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\Hashes;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\NormalizeHashMap;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class NormalizeHashMapTest extends BaseUnitTest {

	private const MD5 = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
	private const SHA1 = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
	private const SHA256 = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

	/**
	 * @dataProvider provideInvalidOuterValues
	 */
	public function test_invalid_outer_values_become_empty_maps( $value ) :void {
		$this->assertSame( [], ( new NormalizeHashMap() )->run( $value ) );
	}

	public function provideInvalidOuterValues() :array {
		return [
			'null'       => [ null ],
			'false'      => [ false ],
			'integer'    => [ 12 ],
			'float'      => [ 1.5 ],
			'string'     => [ self::MD5 ],
			'object'     => [ new \stdClass() ],
			'stringable' => [ new class {
				public function __toString() :string {
					return 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
				}
			} ],
		];
	}

	public function test_mixed_maps_preserve_valid_files_hashes_order_and_duplicates() :void {
		$normalised = ( new NormalizeHashMap() )->run( [
			'src\\File.php' => [
				self::MD5,
				12,
				false,
				null,
				[],
				new \stdClass(),
				self::SHA1,
				self::MD5,
				\strtoupper( self::SHA256 ),
				'not-hex-not-a-supported-hash',
			],
			'style.css' => self::SHA256,
			'empty.php' => [ null, 'bad' ],
		] );

		$this->assertSame( [
			'src/File.php' => [ self::MD5, self::SHA1, self::MD5 ],
			'style.css'    => [ self::SHA256 ],
		], $normalised );
	}

	public function test_invalid_relative_path_keys_are_dropped() :void {
		$normalised = ( new NormalizeHashMap() )->run( [
			''                 => self::MD5,
			12                 => self::MD5,
			'/absolute.php'    => self::MD5,
			'C:\\absolute.php' => self::MD5,
			'../escape.php'    => self::MD5,
			'src/./File.php'   => self::MD5,
			"nul\0file.php"    => self::MD5,
			'Valid/File.php'   => self::MD5,
		] );

		$this->assertSame( [
			'Valid/File.php' => [ self::MD5 ],
		], $normalised );
	}
}
