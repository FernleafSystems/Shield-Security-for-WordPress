<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

class ModeTileBorderStyleContractTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testConfigureTileBorderStyleContract() :void {
		$content = $this->getStylesheetContents( 'assets/css/shield/configure.scss' );

		$this->assertSelectorBlockContains(
			$content,
			'.configure-landing__posture-strip',
			[
				'display: flex;',
				'gap: 1.25rem;',
				'padding: 0.85rem 1.1rem;',
				'margin-bottom: 1.25rem;',
			]
		);

		$this->assertSelectorBlockContains(
			$content,
			'.configure-landing__posture-chip',
			[
				'border-radius: 20px;',
				'font-size: 0.92rem;',
				'font-weight: 700;',
			]
		);

		$this->assertSelectorBlockContains(
			$content,
			'.configure-landing__posture-bar-wrap',
			[
				'height: 10px;',
				'max-width: 320px;',
			]
		);

		$this->assertSelectorBlockContains(
			$content,
			'.configure-landing__posture-bar',
			[
				'border-radius: 6px;',
				'transition: width 0.4s;',
			]
		);

		$this->assertSelectorBlockContains(
			$content,
			'.configure-landing__zone-grid',
			[
				'gap: 0.7rem;',
				'grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));',
			]
		);

		$this->assertSelectorBlockContains(
			$content,
			'.configure-landing__zone-tile',
			[
				'border: 2px solid transparent;',
			]
		);

		$this->assertSelectorBlockContains(
			$content,
			'.configure-landing__zone-tile:hover',
			[
				'border-color: #ccc;',
			]
		);

		$this->assertSelectorBlockContains(
			$content,
			'.configure-landing__zone-tile.status-good.is-active',
			[
				'background: #f8fdf8;',
				'border-color: $status-color-good;',
			]
		);

		$this->assertSelectorBlockContains(
			$content,
			'.configure-landing__zone-tile.status-warning.is-active',
			[
				'background: #fffdf5;',
				'border-color: $status-color-warning;',
			]
		);

		$this->assertSelectorBlockContains(
			$content,
			'.configure-landing__zone-tile.status-critical.is-active',
			[
				'background: #fff8f8;',
				'border-color: $status-color-critical;',
			]
		);

		$this->assertStringNotContainsString( 'border-color: rgba($status-color-good, 0.45);', $content );
		$this->assertStringNotContainsString( 'border-color: rgba($status-color-warning, 0.45);', $content );
		$this->assertStringNotContainsString( 'border-color: rgba($status-color-critical, 0.45);', $content );
		$this->assertStringNotContainsString( 'grid-template-columns: repeat(2, minmax(0, 1fr));', $content );
		$this->assertStringNotContainsString( 'grid-template-columns: repeat(4, minmax(0, 1fr));', $content );
	}

	public function testInvestigateTileStyleContract() :void {
		$content = $this->getStylesheetContents( 'assets/css/shield/investigate.scss' );

		$this->assertSelectorBlockContains(
			$content,
			'.investigate-landing__subject-grid',
			[
				'gap: 0.7rem;',
				'grid-template-columns: repeat(auto-fill, minmax(175px, 1fr));',
			]
		);

		$subjectCardBody = $this->assertSelectorBlockContains(
			$content,
			'.investigate-landing__subject-card',
			[
				'border: 2px solid transparent;',
				'align-items: flex-start;',
				'text-align: left;',
				'position: relative;',
			]
		);
		$this->assertStringNotContainsString( 'justify-content: center;', $subjectCardBody );

		$this->assertSelectorBlockContains(
			$content,
			'.investigate-landing__subject-label',
			[
				'justify-content: flex-start;',
			]
		);

		$this->assertSelectorBlockContains(
			$content,
			'.investigate-landing__subject-card::before',
			[
				'background: $status-color-info;',
				'opacity: 0;',
				'transition: opacity 0.2s;',
			]
		);

		$this->assertMatchesRegularExpression(
			'/\.investigate-landing__subject-card:not\(\.is-disabled\):hover::before,\s*\.investigate-landing__subject-card\.is-active::before\s*\{[^}]*\bopacity:\s*1;[^}]*\}/s',
			$content
		);

		$this->assertStringNotContainsString( 'grid-template-columns: repeat(2, minmax(0, 1fr));', $content );
		$this->assertStringNotContainsString( 'grid-template-columns: repeat(3, minmax(0, 1fr));', $content );
		$this->assertStringNotContainsString( 'grid-template-columns: repeat(4, minmax(0, 1fr));', $content );
		$this->assertStringNotContainsString( 'grid-template-columns: repeat(6, minmax(0, 1fr));', $content );
	}

	private function getStylesheetContents( string $path ) :string {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'assets/css source stylesheets are excluded from packaged artifacts' );
		}

		return $this->getPluginFileContents( $path, 'stylesheet' );
	}

	/**
	 * @param string[] $expectedSnippets
	 */
	private function assertSelectorBlockContains( string $content, string $selector, array $expectedSnippets ) :string {
		$body = $this->extractSelectorBody( $content, $selector );
		$normalizedBody = $this->normalizeWhitespace( $body );

		foreach ( $expectedSnippets as $snippet ) {
			$this->assertStringContainsString(
				$this->normalizeWhitespace( $snippet ),
				$normalizedBody,
				\sprintf( 'Expected selector "%s" block to include: %s', $selector, $snippet )
			);
		}

		return $normalizedBody;
	}

	private function extractSelectorBody( string $content, string $selector ) :string {
		$matched = \preg_match(
			'/'.\preg_quote( $selector, '/' ).'\s*\{(?P<body>[^}]*)\}/s',
			$content,
			$matches
		);

		$this->assertSame( 1, $matched, \sprintf( 'Selector block not found: %s', $selector ) );
		return (string)( $matches[ 'body' ] ?? '' );
	}

	private function normalizeWhitespace( string $value ) :string {
		return (string)\preg_replace( '/\s+/', ' ', \trim( $value ) );
	}
}
