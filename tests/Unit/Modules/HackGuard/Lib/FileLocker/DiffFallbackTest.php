<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\FileLocker;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\Diff;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\InvokesNonPublicMethods;

class DiffFallbackTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	public function test_wp_diff_fallback_limits_context_lines() :void {
		$original = "one\ntwo\nthree\nfour\nfive\nsix\nseven\n";
		$current = "one\ntwo\nthree\nchanged\nfive\nsix\nseven\n";
		$expectedHtml = '<table class="diff">unit fallback diff</table>';

		Functions\expect( 'wp_text_diff' )
			->once()
			->with(
				$original,
				$current,
				\Mockery::on( static fn( array $args ) :bool =>
					( $args[ 'show_split_view' ] ?? null ) === true
					&& ( $args[ 'leading_context_lines' ] ?? null ) === 3
					&& ( $args[ 'trailing_context_lines' ] ?? null ) === 3
				)
			)->andReturn( $expectedHtml );

		$this->assertSame(
			$expectedHtml,
			$this->invokeNonPublicMethod( new Diff(), 'useWpDiff', [ $original, $current ] )
		);
	}
}
