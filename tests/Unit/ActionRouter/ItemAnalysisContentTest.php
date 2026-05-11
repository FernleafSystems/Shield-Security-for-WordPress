<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ItemAnalysis\Content;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\Fs;

class ItemAnalysisContentTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_wpfs' => new ItemAnalysisContentFs(),
		] );
		Functions\when( 'path_join' )->alias(
			static fn( string $base, string $path ) :string => \rtrim( $base, '/\\' ).'/'.\ltrim( $path, '/\\' )
		);
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	/**
	 * @dataProvider providerFilePathToHighlightLanguage
	 */
	public function testRenderDataMapsFilePathToHighlightLanguage( string $path, string $expectedLanguage ) :void {
		$fullPath = \rtrim( ABSPATH, '/\\' ).'/'.\ltrim( $path, '/\\' );
		if ( !\is_dir( \dirname( $fullPath ) ) ) {
			\mkdir( \dirname( $fullPath ), 0777, true );
		}
		\file_put_contents( $fullPath, "alpha\tbeta\r\nnext" );

		$item = new ResultItem();
		$item->path_fragment = $path;

		try {
			$renderData = ( new ItemAnalysisContentTestDouble( [
				'scan_item' => $item,
			] ) )->renderDataForTest();

			$this->assertSame( $expectedLanguage, $renderData[ 'code_language' ] );
			$this->assertSame( [ 'alpha    beta', 'next' ], $renderData[ 'lines' ] );
		}
		finally {
			@\unlink( $fullPath );
		}
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

class ItemAnalysisContentTestDouble extends Content {

	public function renderDataForTest() :array {
		return $this->getRenderData();
	}
}

class ItemAnalysisContentFs extends Fs {

	public function isAccessibleFile( string $path ) :bool {
		return \is_file( $path );
	}

	public function getFileContent( $path, $uncompress = false ) {
		return \file_get_contents( (string)$path );
	}
}
