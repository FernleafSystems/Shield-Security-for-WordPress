<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\FileLocker;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\PrepareWpDiffInput;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class PrepareWpDiffInputTest extends TestCase {

	public function test_empty_strings_are_unchanged() :void {
		$this->assertSame(
			[
				'original' => '',
				'current'  => '',
			],
			( new PrepareWpDiffInput() )->run( '', '' )
		);
	}

	/**
	 * @dataProvider provideValidInputs
	 */
	public function test_valid_utf8_pairs_are_unchanged( string $original, string $current ) :void {
		$this->assertSame(
			[
				'original' => $original,
				'current'  => $current,
			],
			( new PrepareWpDiffInput() )->run( $original, $current )
		);
	}

	public function provideValidInputs() :array {
		return [
			'ascii'     => [ 'plain ASCII', "tabs\tand\r\nlines" ],
			'multibyte' => [ "caf\xC3\xA9", "\xE6\x9D\xB1\xE4\xBA\xAC" ],
		];
	}

	/**
	 * @dataProvider provideInvalidInputs
	 */
	public function test_invalidity_on_either_side_encodes_both(
		string $original,
		string $current,
		string $expectedOriginal,
		string $expectedCurrent
	) :void {
		$this->assertSame(
			[
				'original' => $expectedOriginal,
				'current'  => $expectedCurrent,
			],
			( new PrepareWpDiffInput() )->run( $original, $current )
		);
	}

	public function provideInvalidInputs() :array {
		return [
			'original invalid' => [
				'old '.\chr( 0xC3 ),
				'literal \\xC3',
				'old \\xC3',
				'literal \\\\xC3',
			],
			'current invalid'  => [
				'literal \\xC3',
				'new '.\chr( 0xC3 ),
				'literal \\\\xC3',
				'new \\xC3',
			],
			'both invalid'     => [
				\chr( 0xC3 ),
				\chr( 0xC3 ),
				'\\xC3',
				'\\xC3',
			],
		];
	}

	public function test_invalid_bytes_remain_distinguishable() :void {
		$this->assertSame(
			[
				'original' => '\\xC3',
				'current'  => '\\xC4',
			],
			( new PrepareWpDiffInput() )->run( \chr( 0xC3 ), \chr( 0xC4 ) )
		);
	}

	public function test_literal_byte_marker_is_distinct_from_encoded_byte() :void {
		$this->assertSame(
			[
				'original' => 'literal \\\\xC3',
				'current'  => 'byte \\xC3',
			],
			( new PrepareWpDiffInput() )->run( 'literal \\xC3', 'byte '.\chr( 0xC3 ) )
		);
	}

	public function test_invalid_mode_preserves_line_endings_and_tab_and_encodes_other_bytes() :void {
		$original = "\r\nline\nlone\r\t".
			\chr( 0x00 ).\chr( 0x01 ).\chr( 0x0F ).\chr( 0x7F ).\chr( 0x80 ).'\\';

		$this->assertSame(
			[
				'original' => "\r\nline\nlone\r\t\\x00\\x01\\x0F\\x7F\\x80\\\\",
				'current'  => '\\xC3',
			],
			( new PrepareWpDiffInput() )->run( $original, \chr( 0xC3 ) )
		);
	}
}
