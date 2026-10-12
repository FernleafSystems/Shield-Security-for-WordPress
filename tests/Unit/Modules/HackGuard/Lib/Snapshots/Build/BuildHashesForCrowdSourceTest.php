<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\Snapshots\Build;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build\BuildHashesForCrowdSource;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\Fs;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;

class BuildHashesForCrowdSourceTest extends BaseUnitTest {

	use TempDirLifecycleTrait;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'wp_normalize_path' )->alias( static fn( string $path ) :string => \str_replace( '\\', '/', $path ) );
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [ 'service_wpfs' => new BuildHashesForCrowdSourceFs() ] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function test_text_files_have_lf_line_endings_and_binary_files_keep_their_bytes() :void {
		$text = "<?php\r\necho 1;\rdone\r\n";
		$icon = "\0\0\x01\0\r\nicon\rdata\r\n";
		// Only a NUL byte within the first 8,000 bytes makes a file binary.
		$probeEdge = \substr_replace( \str_repeat( "x\r\n", 3000 ), "\0", 7999, 1 );
		$pastProbe = \substr_replace( \str_repeat( "x\r\n", 3000 ), "\0", 8000, 1 );
		$this->assertSame( [
			'edge.mo'  => \sha1( $probeEdge ),
			'icon.ico' => \sha1( $icon ),
			'main.php' => \sha1( "<?php\necho 1;\ndone\n" ),
			'past.js'  => \sha1( \str_replace( [ "\r\n", "\r" ], "\n", $pastProbe ) ),
		], $this->build( [
			'main.php'  => $text,
			'icon.ico'  => $icon,
			'edge.mo'   => $probeEdge,
			'past.js'   => $pastProbe,
			'notes.txt' => $text,
		] ) );
	}

	public function test_submitted_hashes_equal_the_hashes_the_api_publishes_for_the_same_bytes() :void {
		// The API's canonical fixture pack publishes these SHA-1s for these bytes; a crowd-sourced collection
		// only verifies against the API's WordPress.org hashes when Shield computes identical values.
		$this->assertSame( [
			'data.ico'  => 'fe14f7784b0f681e11f61de1240412f4f18a1e7b',
			'main.php'  => '5ba6310fcdb87203c54262628a243bcc689b7963',
			'style.css' => '5ba6310fcdb87203c54262628a243bcc689b7963',
		], $this->build( [
			'data.ico'  => (string)\base64_decode( 'AAENCgL/DQM=', true ),
			'main.php'  => "line-1\r\nline-2\r\n",
			'style.css' => "line-1\rline-2\r",
		] ) );
	}

	/**
	 * @param array<string,string> $files
	 * @return array<string,string>
	 */
	private function build( array $files ) :array {
		$dir = $this->createTrackedTempDir( 'shield-crowd-source-' );
		foreach ( $files as $name => $contents ) {
			\file_put_contents( $dir.'/'.$name, $contents );
		}
		return ( new BuildHashesForCrowdSource() )->build(
			new BuildHashesForCrowdSourcePluginVo( \str_replace( '\\', '/', $dir ).'/' ),
			[ 'php', 'ico', 'mo', 'js', 'css' ]
		);
	}
}

class BuildHashesForCrowdSourcePluginVo extends WpPluginVo {

	private string $installDir;

	public function __construct( string $installDir ) {
		$this->installDir = $installDir;
	}

	public function getInstallDir() :string {
		return $this->installDir;
	}
}

class BuildHashesForCrowdSourceFs extends Fs {

	public function getFileContent( $path, $uncompress = false ) {
		unset( $uncompress );
		return \file_get_contents( $path );
	}
}
