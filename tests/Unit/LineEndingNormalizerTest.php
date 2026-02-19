<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Output\LineEndingNormalizer;
use PHPUnit\Framework\TestCase;

class LineEndingNormalizerTest extends TestCase {

	public function testToLfNormalizesCrLfAndCr() :void {
		$normalizer = new LineEndingNormalizer();
		$raw = "line1\rline2\r\nline3\n";

		$this->assertSame(
			"line1\nline2\nline3\n",
			$normalizer->toLf( $raw )
		);
	}

	public function testToHostEolMapsLfToHostLineEndings() :void {
		$normalizer = new LineEndingNormalizer();
		$raw = "line1\rline2\r\nline3\n";

		$this->assertSame(
			\implode( \PHP_EOL, [ 'line1', 'line2', 'line3', '' ] ),
			$normalizer->toHostEol( $raw )
		);
	}
}

