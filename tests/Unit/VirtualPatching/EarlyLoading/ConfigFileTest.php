<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching\EarlyLoading;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading\ConfigFile;
use PHPUnit\Framework\TestCase;

class ConfigFileTest extends TestCase {

	use TempDirLifecycleTrait;

	private string $dir;

	protected function setUp() :void {
		parent::setUp();
		$this->dir = $this->createTrackedTempDir( 'shield-early-config-' );
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testModifyRewritesExistingContentAndReportsBeforeAndAfter() :void {
		$path = $this->dir.'/.htaccess';
		\file_put_contents( $path, "long original content that is longer than the replacement\n" );

		[ $before, $after ] = ( new ConfigFile( $path ) )->modify( fn( ?string $c ) => 'short' );

		$this->assertSame( "long original content that is longer than the replacement\n", $before );
		$this->assertSame( 'short', $after );
		$this->assertSame( 'short', \file_get_contents( $path ) );
	}

	public function testModifyCreatesMissingFileAndPassesNull() :void {
		$path = $this->dir.'/.user.ini';
		$seen = 'unset';

		( new ConfigFile( $path ) )->modify( function ( ?string $c ) use ( &$seen ) {
			$seen = $c;
			return "auto_prepend_file = '/s.php'\n";
		} );

		$this->assertNull( $seen );
		$this->assertSame( "auto_prepend_file = '/s.php'\n", \file_get_contents( $path ) );
	}

	public function testModifyLeavesNoFileWhenNothingIsWrittenToAMissingFile() :void {
		$path = $this->dir.'/.user.ini';

		( new ConfigFile( $path ) )->modify( fn( ?string $c ) => null );
		( new ConfigFile( $path ) )->modify( fn( ?string $c ) => '' );

		$this->assertFileDoesNotExist( $path );
	}

	public function testModifyLeavesNoFileWhenTheChangeFailsOnAMissingFile() :void {
		$path = $this->dir.'/.user.ini';

		try {
			( new ConfigFile( $path ) )->modify( function () {
				throw new \RuntimeException( 'refused' );
			} );
			$this->fail( 'Expected the change to throw.' );
		}
		catch ( \RuntimeException $e ) {
			$this->assertSame( 'refused', $e->getMessage() );
		}

		$this->assertFileDoesNotExist( $path );
	}

	public function testRestorePutsBackExactContentOrRemovesTheFile() :void {
		$existing = $this->dir.'/.htaccess';
		\file_put_contents( $existing, "original\r\n" );
		$created = $this->dir.'/.user.ini';
		\file_put_contents( $created, 'added by install' );

		( new ConfigFile( $existing ) )->restore( "original\r\n" );
		( new ConfigFile( $created ) )->restore( null );

		$this->assertSame( "original\r\n", \file_get_contents( $existing ) );
		$this->assertFileDoesNotExist( $created );
	}

	public function testReadReturnsNullForMissingFile() :void {
		$this->assertNull( ( new ConfigFile( $this->dir.'/missing.ini' ) )->read() );
	}
}
