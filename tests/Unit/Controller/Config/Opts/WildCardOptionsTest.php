<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Controller\Config\Opts;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\WildCardOptions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class WildCardOptionsTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'wp_normalize_path' )->alias(
			static fn( string $path ) :string => \str_replace( '\\', '/', $path )
		);
		Functions\when( 'path_join' )->alias(
			static fn( string $base, string $path ) :string => \rtrim( $base, '/\\' ).'/'.\ltrim( $path, '/\\' )
		);
		Functions\when( 'trailingslashit' )->alias(
			static fn( string $path ) :string => \rtrim( $path, '/\\' ).'/'
		);
		Functions\when( 'untrailingslashit' )->alias(
			static fn( string $path ) :string => \rtrim( $path, '/\\' )
		);
	}

	public function test_file_path_cleaning_filters_malformed_members_without_losing_valid_siblings() :void {
		$cleaned = ( new WildCardOptions() )->clean( [
			'  WP-CONTENT\CACHE\*  ',
			'wp-content/cache/*',
			'wp-content/custom[dir]/*.PHP',
			12,
			false,
			null,
			[],
			'',
		], [], WildCardOptions::FILE_PATH_REL );

		$this->assertSame( [
			'wp-content/cache/*',
			'wp-content/custom[dir]/*.php',
		], $cleaned );
	}

	public function test_file_path_regex_preserves_wildcard_and_literal_metacharacter_semantics() :void {
		$wildcards = new WildCardOptions();
		$literalPattern = $wildcards->buildFullRegexValue(
			'wp-content/custom[dir]/release+candidate/*.php',
			WildCardOptions::FILE_PATH_REL
		);
		$subtreePattern = $wildcards->buildFullRegexValue(
			'wp-content/cache/',
			WildCardOptions::FILE_PATH_REL
		);

		$this->assertMatchesRegularExpression(
			$literalPattern,
			$this->absolutePath( 'wp-content/custom[dir]/release+candidate/example.php' )
		);
		$this->assertDoesNotMatchRegularExpression(
			$literalPattern,
			$this->absolutePath( 'wp-content/customXdir/releasecandidate/example.php' )
		);
		$this->assertDoesNotMatchRegularExpression(
			$literalPattern,
			$this->absolutePath( 'wp-content/custom[dir]/release+candidate/example.phps' )
		);
		$this->assertMatchesRegularExpression(
			$subtreePattern,
			$this->absolutePath( 'wp-content/cache/nested/item.dat' )
		);
		$this->assertDoesNotMatchRegularExpression(
			$subtreePattern,
			$this->absolutePath( 'wp-content/cache-copy/item.dat' )
		);
	}

	public function test_file_path_regex_normalizes_windows_root_before_compilation() :void {
		Functions\when( 'wp_normalize_path' )->alias(
			static fn( string $path ) :string => $path === ABSPATH
				? 'C:/WordPress/'
				: \str_replace( '\\', '/', $path )
		);
		Functions\when( 'path_join' )->alias(
			static fn( string $base, string $path ) :string => $base === ABSPATH
				? 'C:\\WordPress\\'.\ltrim( $path, '/\\' )
				: \rtrim( $base, '/\\' ).'/'.\ltrim( $path, '/\\' )
		);

		$pattern = ( new WildCardOptions() )->buildFullRegexValue(
			'wp-content/cache/*',
			WildCardOptions::FILE_PATH_REL
		);

		$this->assertMatchesRegularExpression( $pattern, 'C:/WordPress/wp-content/cache/item.dat' );
	}

	public function test_file_path_cleaning_rejects_protected_path_without_rejecting_valid_sibling() :void {
		$cleaned = ( new WildCardOptions() )->clean(
			[ 'wp-admin/*', 'wp-content/cache/*' ],
			[ $this->absolutePath( 'wp-admin/' ) ],
			WildCardOptions::FILE_PATH_REL
		);

		$this->assertSame( [ 'wp-content/cache/*' ], $cleaned );
	}

	private function absolutePath( string $relativePath ) :string {
		return \rtrim( \str_replace( '\\', '/', ABSPATH ), '/' ).'/'.\ltrim( $relativePath, '/' );
	}
}
