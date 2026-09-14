<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\FileLocker;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\Diff;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\DiffUnavailableException;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class DiffFallbackTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
	}

	public function test_wp_diff_fallback_preserves_valid_input_and_limits_context_lines() :void {
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
			( new DiffFallbackProbe() )->run( $original, $current )
		);
	}

	public function test_invalid_input_is_encoded_on_both_sides_before_fallback() :void {
		$original = 'before '.\chr( 0xC3 ).' after';
		$current = 'literal \\xC3 changed';
		$expectedHtml = '<table class="diff">encoded fallback diff</table>';

		Functions\expect( 'wp_text_diff' )
			->once()
			->with(
				'before \\xC3 after',
				'literal \\\\xC3 changed',
				\Mockery::type( 'array' )
			)
			->andReturn( $expectedHtml );

		$this->assertSame(
			$expectedHtml,
			( new DiffFallbackProbe() )->run( $original, $current )
		);
	}

	/**
	 * @dataProvider provideApiThrowables
	 */
	public function test_api_throwable_selects_prepared_wp_fallback( \Throwable $failure ) :void {
		$original = 'old '.\chr( 0xC3 );
		$current = 'new content';
		$expectedHtml = '<table class="diff">fallback diff</table>';
		$subject = new DiffFallbackProbe();
		$subject->requestThrowable = $failure;

		Functions\expect( 'wp_text_diff' )
			->once()
			->with( 'old \\xC3', 'new content', \Mockery::type( 'array' ) )
			->andReturn( $expectedHtml );

		$this->assertSame( $expectedHtml, $subject->run( $original, $current ) );
	}

	public function provideApiThrowables() :array {
		return [
			'exception' => [ new \RuntimeException( 'API exception' ) ],
			'error'     => [ new \TypeError( 'API type error' ) ],
		];
	}

	/**
	 * @dataProvider provideUnusableApiResponses
	 */
	public function test_unusable_api_response_selects_wp_fallback( ?array $response ) :void {
		$expectedHtml = '<table class="diff">fallback diff</table>';
		$subject = new DiffFallbackProbe();
		$subject->response = $response;

		Functions\expect( 'wp_text_diff' )
			->once()
			->andReturn( $expectedHtml );

		$this->assertSame( $expectedHtml, $subject->run( 'original', 'current' ) );
	}

	public function provideUnusableApiResponses() :array {
		$validContent = \base64_encode( '<table>diff</table>' );
		$validCss = \base64_encode( '.diff{}' );

		return [
			'null response'         => [ null ],
			'missing html'          => [ [] ],
			'html is not an array'  => [ [ 'html' => 'invalid' ] ],
			'missing content'       => [ [ 'html' => [ 'css_default' => $validCss ] ] ],
			'missing css'           => [ [ 'html' => [ 'content' => $validContent ] ] ],
			'content is not string' => [ [ 'html' => [ 'content' => 1, 'css_default' => $validCss ] ] ],
			'css is not string'     => [ [ 'html' => [ 'content' => $validContent, 'css_default' => 1 ] ] ],
			'content is empty'      => [ [ 'html' => [ 'content' => '', 'css_default' => $validCss ] ] ],
			'css is empty'          => [ [ 'html' => [ 'content' => $validContent, 'css_default' => '' ] ] ],
			'content is not base64' => [ [ 'html' => [ 'content' => '!', 'css_default' => $validCss ] ] ],
			'css is not base64'     => [ [ 'html' => [ 'content' => $validContent, 'css_default' => '!' ] ] ],
			'decoded content empty' => [ [ 'html' => [ 'content' => ' ', 'css_default' => $validCss ] ] ],
			'decoded css empty'     => [ [ 'html' => [ 'content' => $validContent, 'css_default' => ' ' ] ] ],
		];
	}

	public function test_usable_api_response_is_rendered_without_wp_fallback() :void {
		$original = 'original '.\chr( 0xC3 );
		$current = "current\0";
		$content = '<table class="diff">API diff</table>';
		$css = '.diff{color:red;}';
		$subject = new DiffFallbackProbe();
		$subject->response = [
			'html' => [
				'content'     => \base64_encode( $content ),
				'css_default' => \base64_encode( $css ),
			],
		];

		Functions\expect( 'wp_text_diff' )->never();

		$this->assertSame(
			'<style>'.
			'table.diff.diff-wrapper tbody tr td:nth-child(2){ width:auto;}'.
			'table.diff.diff-wrapper { table-layout: auto;}'.
			$css.'</style>'.$content,
			$subject->run( $original, $current )
		);
		$this->assertSame( [ [ $original, $current ] ], $subject->requests );
	}

	public function test_wp_diff_throwable_becomes_safe_chained_exception() :void {
		$original = 'sensitive original content';
		$current = 'sensitive current content';
		$failure = new \TypeError( 'raw mocked wp_text_diff failure' );

		Functions\expect( 'wp_text_diff' )
			->once()
			->andThrow( $failure );

		try {
			( new DiffFallbackProbe() )->run( $original, $current );
			$this->fail( 'Expected the WordPress diff failure to be contained.' );
		}
		catch ( DiffUnavailableException $e ) {
			$this->assertSame( 'The file comparison could not be generated.', $e->getMessage() );
			$this->assertSame( $failure, $e->getPrevious() );
			$this->assertStringNotContainsString( $failure->getMessage(), $e->getMessage() );
			$this->assertStringNotContainsString( $original, $e->getMessage() );
			$this->assertStringNotContainsString( $current, $e->getMessage() );
		}
	}
}

class DiffFallbackProbe extends Diff {

	public $requestThrowable;

	public ?array $response = null;

	public array $requests = [];

	protected function requestWpHashesDiff( string $original, string $current ) :?array {
		$this->requests[] = [ $original, $current ];

		if ( $this->requestThrowable instanceof \Throwable ) {
			throw $this->requestThrowable;
		}

		return $this->response;
	}
}
