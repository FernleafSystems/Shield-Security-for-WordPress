<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\MU;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\MU\GeneratedMuLoaderContent;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory
};

/**
 * The generated MU loader is a PHP file that loads on every request, and its name, URL and author come from
 * white-label settings. No saved value may stop that file parsing or change what it loads.
 */
class GeneratedMuLoaderContentTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_white_label_values_with_php_syntax_still_build_a_loader_that_parses() :void {
		$name = "O'Reilly */ 100% Security \\' \\";
		$rootFile = "C:\\sites\\it's\\wp-content\\plugins\\wp-simple-firewall\\icwp-wpsf.php";
		$content = $this->build( $name, 'https://example.test/a*/b', "Acme */ Co\nAuthor URI: https://evil.test", $rootFile );

		$tokens = \token_get_all( $content, \TOKEN_PARSE );

		$strings = $this->stringLiteralValues( $tokens );
		$this->assertContains( $rootFile, $strings, 'The loader must require the exact root file.' );
		$notices = \array_values( \array_filter( $strings, fn( string $s ) => \str_contains( $s, 'plugin appears' ) ) );
		$this->assertCount( 1, $notices );
		$this->assertStringContainsString(
			'The '.$name.' plugin appears',
			\sprintf( $notices[ 0 ], '/mu-plugins/a-shield-mu.php' ),
			'The removal notice, as sprintf() prints it, must show the name exactly as saved.'
		);

		$comments = \array_values( \array_filter( $tokens, fn( $t ) => \is_array( $t ) && $t[ 0 ] === \T_COMMENT ) );
		$this->assertCount( 1, $comments, 'Only the plugin header should be a comment.' );
		$this->assertStringContainsString( "Plugin Name: O'Reilly * / 100% Security", $comments[ 0 ][ 1 ] );
		$this->assertStringNotContainsString( "\nAuthor URI: https://evil.test", $comments[ 0 ][ 1 ] );
	}

	/**
	 * The cloaked-plugins check recognises Shield's loader by comparing the file with freshly built content,
	 * so values that already built a working loader must build exactly the same content as before.
	 */
	public function test_values_that_already_built_a_working_loader_build_the_same_content() :void {
		$rootFile = "C:\\sites\\wp\\wp-content\\plugins\\wp-simple-firewall\\icwp-wpsf.php";
		$template = (string)\file_get_contents( $this->templatePath() );
		$previous = \str_replace(
			[ 'SHIELD_ROOT_FILE_PHP_STRING', 'SHIELD_PLUGIN_NAME_IN_NOTICE', 'SHIELD_PLUGIN_NAME', 'SHIELD_PLUGIN_URL', 'SHIELD_PLUGIN_AUTHOR' ],
			[ $rootFile, 'Acme & Co Guard', 'Acme & Co Guard', 'https://example.test/shield', 'Acme & Co' ],
			$template
		);

		$this->assertSame(
			$previous,
			$this->build( 'Acme & Co Guard', 'https://example.test/shield', 'Acme & Co', $rootFile )
		);
	}

	private function build( string $name, string $url, string $author, string $rootFile ) :string {
		UnitTestControllerFactory::install( null, null, (object)[
			'root_file' => $rootFile,
			'labels'    => (object)[
				'Name'      => $name,
				'PluginURI' => $url,
				'Author'    => $author,
			],
		] );
		return ( new GeneratedMuLoaderContent() )->build();
	}

	/**
	 * @return string[]
	 */
	private function stringLiteralValues( array $tokens ) :array {
		$values = [];
		foreach ( $tokens as $token ) {
			if ( \is_array( $token ) && $token[ 0 ] === \T_CONSTANT_ENCAPSED_STRING ) {
				$values[] = eval( 'return '.$token[ 1 ].';' );
			}
		}
		return $values;
	}

	private function templatePath() :string {
		return \dirname( __DIR__, 5 ).'/src/Components/CompCons/MU/mu-template.txt';
	}
}
