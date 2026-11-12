<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Scans\Afs\Scans;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Exceptions\WpContentFileUnidentifiedException;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans\WpContentUnidentified;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\Fs;

class WpContentUnidentifiedTest extends BaseUnitTest {

	private const MD5 = '0a4303d4046f7d1b3ebe72ff19901df9';

	private array $servicesSnapshot = [];

	private string $content;

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		Functions\when( 'wp_normalize_path' )->alias(
			static fn( $path ) :string => (string)\preg_replace( '|(?<=.)/+|', '/', \str_replace( '\\', '/', (string)$path ) )
		);
		Functions\when( 'path_join' )->alias(
			static fn( string $base, string $path ) :string => \rtrim( $base, '/\\' ).'/'.\ltrim( $path, '/\\' )
		);
		$this->content = \rtrim( \str_replace( '\\', '/', ABSPATH ), '/' ).'/wp-content';
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_unidentified_file_in_wp_content_is_reported() :void {
		$this->expectException( WpContentFileUnidentifiedException::class );
		$this->scan( $this->content.'/litespeed/shell.php' );
	}

	public function test_litespeed_optimised_js_is_not_reported() :void {
		foreach (
			[
				'single site' => $this->content.'/litespeed/js/'.self::MD5.'.js',
				'multisite'   => $this->content.'/litespeed/js/2/'.self::MD5.'.js',
			] as $case => $path
		) {
			$this->assertFalse( $this->scan( $path ), $case );
		}
	}

	public function test_siteground_combined_js_is_not_reported() :void {
		$this->assertFalse( $this->scan(
			$this->content.'/uploads/siteground-optimizer-assets/siteground-optimizer-combined-js-'.self::MD5.'.js'
		) );
	}

	public function test_files_that_only_resemble_litespeed_output_are_reported() :void {
		foreach (
			[
				'non-hash name'  => $this->content.'/litespeed/js/evil.js',
				'short hash'     => $this->content.'/litespeed/js/'.\substr( self::MD5, 1 ).'.js',
				'long hash'      => $this->content.'/litespeed/js/'.self::MD5.'0.js',
				'non-hex hash'   => $this->content.'/litespeed/js/'.\substr( self::MD5, 1 ).'z.js',
				'php extension'  => $this->content.'/litespeed/js/'.self::MD5.'.php',
				'other folder'   => $this->content.'/litespeed/other/'.self::MD5.'.js',
				'deeper folder'  => $this->content.'/litespeed/js/sub/'.self::MD5.'.js',
			] as $case => $path
		) {
			try {
				$this->scan( $path );
				$this->fail( 'Expected file to be reported: '.$case );
			}
			catch ( WpContentFileUnidentifiedException $e ) {
				$this->addToAssertionCount( 1 );
			}
		}
	}

	private function scan( string $path ) :bool {
		ServicesState::installItems( [
			'service_wpfs' => new WpContentUnidentifiedTestFs( [ $path ] ),
		] );
		return ( new WpContentUnidentified( $path ) )->isFileValid();
	}
}

class WpContentUnidentifiedTestFs extends Fs {

	private array $accessibleFiles;

	public function __construct( array $accessibleFiles ) {
		$this->accessibleFiles = $accessibleFiles;
	}

	public function isAccessibleFile( string $path ) :bool {
		return \in_array( $path, $this->accessibleFiles, true );
	}
}
