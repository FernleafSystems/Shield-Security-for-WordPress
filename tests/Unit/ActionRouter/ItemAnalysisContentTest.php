<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ItemAnalysis\Content;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ItemAnalysisContentTest extends BaseUnitTest {

	public function testClassExists() :void {
		$this->assertTrue( \class_exists( Content::class ) );
	}

	/**
	 * @dataProvider providerFilePathToHighlightLanguage
	 */
	public function testGetCodeLanguageMapping( string $path, string $expectedLanguage ) :void {
		$content = new Content();
		$reflection = new \ReflectionClass( $content );
		$method = $reflection->getMethod( 'getCodeLanguage' );
		$method->setAccessible( true );

		$this->assertSame( $expectedLanguage, $method->invoke( $content, $path ) );
	}

	public static function providerFilePathToHighlightLanguage() :array {
		return [
			'php extension' => [ 'wp-content/plugins/test/file.php', 'php' ],
			'php variant extension uppercase' => [ 'wp-content/plugins/test/file.PHP7', 'php' ],
			'phtml extension' => [ 'wp-content/plugins/test/file.phtml', 'php' ],
			'javascript extension' => [ 'wp-content/themes/test/file.js', 'javascript' ],
			'javascript module extension' => [ 'wp-content/themes/test/file.mjs', 'javascript' ],
			'css extension' => [ 'wp-content/themes/test/file.css', 'css' ],
			'json extension uppercase' => [ 'wp-content/themes/test/file.JSON', 'json' ],
			'xml family extension' => [ 'wp-content/themes/test/file.svg', 'xml' ],
			'bash extension' => [ 'wp-content/themes/test/file.bash', 'bash' ],
			'sql extension' => [ 'wp-content/themes/test/file.sql', 'sql' ],
			'unknown extension' => [ 'wp-content/themes/test/file.unknown', 'unknown' ],
			'no extension' => [ 'wp-content/themes/test/file', '' ],
		];
	}
}
