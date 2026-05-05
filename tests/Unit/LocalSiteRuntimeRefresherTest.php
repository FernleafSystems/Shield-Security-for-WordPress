<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteRuntimeRefresher;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ScriptedProcessRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class LocalSiteRuntimeRefresherTest extends TestCase {

	use TempDirLifecycleTrait;

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = $this->createTrackedTempDir( 'shield-runtime-refresher-' );
		$this->seedRuntimeFiles( $this->projectRoot );
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testRefreshSeedsWhenRuntimeIsEmpty() :void {
		$runner = new ScriptedProcessRunner( $this->emptyRuntimeSeedResponses() );
		$refresher = new LocalSiteRuntimeRefresher( $runner );

		\ob_start();
		try {
			$refresher->refresh( $this->projectRoot, 'wordpress-container' );
		}
		finally {
			\ob_end_clean();
		}

		$this->assertCount( 7, $runner->calls );
		$this->assertSame( 'tar', $runner->calls[ 1 ][ 'command' ][ 0 ] );
		$this->assertSame(
			'wordpress-container:/tmp/shield-browser-runtime-refresh.tar',
			$runner->calls[ 2 ][ 'command' ][ 3 ]
		);

		$fileList = (string)\file_get_contents( Path::join( $this->runtimeWorkspace(), 'runtime-files.txt' ) );
		$this->assertStringContainsString( "assets/images/logo.png\n", $fileList );
		$this->assertStringContainsString( "flags/index.html\n", $fileList );
		$this->assertStringContainsString( "vendor/autoload.php\n", $fileList );
		$this->assertStringContainsString( "tests/Helpers/BrowserFixtureRegistry.php\n", $fileList );
		$this->assertStringContainsString( "tests/Helpers/ActionRouter/ActionsQueueFixtureBuilder.php\n", $fileList );
		$this->assertStringContainsString( "tests/Helpers/CrossSite/CrossSiteRuntime.php\n", $fileList );
		$this->assertStringContainsString( "tests/browser/support/shield-browser-fixtures.php\n", $fileList );
	}

	public function testRefreshRoutesSubprocessOutputThroughProvidedCallback() :void {
		$runner = new ScriptedProcessRunner( $this->emptyRuntimeSeedResponses() );
		$refresher = new LocalSiteRuntimeRefresher( $runner );

		$refresher->refresh( $this->projectRoot, 'wordpress-container', static function () :void {} );

		$this->assertCount( 7, $runner->calls );
		foreach ( $runner->calls as $call ) {
			$this->assertTrue( $call[ 'has_output_callback' ], \implode( ' ', $call[ 'command' ] ) );
		}
	}

	public function testRefreshSkipsWhenManifestMatches() :void {
		$manifest = $this->buildManifestForFixture();
		$runner = new ScriptedProcessRunner( [
			[
				'exit_code' => 0,
				'stdout' => \json_encode( [
					'manifest_exists' => true,
					'sentinels' => [
						'icwp-wpsf.php' => true,
						'plugin.json' => true,
					],
					'all_required_sentinels_present' => true,
					'has_any_required_sentinel' => true,
				], \JSON_UNESCAPED_SLASHES ) ?: '',
			],
			[
				'exit_code' => 0,
				'stdout' => \json_encode( $manifest, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES ) ?: '',
			],
		] );
		$refresher = new LocalSiteRuntimeRefresher( $runner );

		\ob_start();
		try {
			$refresher->refresh( $this->projectRoot, 'wordpress-container' );
		}
		finally {
			\ob_end_clean();
		}

		$this->assertCount( 2, $runner->calls );
	}

	public function testRefreshFailsFastOnInconsistentRuntimeState() :void {
		$runner = new ScriptedProcessRunner( [
			[
				'exit_code' => 0,
				'stdout' => \json_encode( [
					'manifest_exists' => false,
					'sentinels' => [
						'icwp-wpsf.php' => true,
						'plugin.json' => false,
					],
					'all_required_sentinels_present' => false,
					'has_any_required_sentinel' => true,
				], \JSON_UNESCAPED_SLASHES ) ?: '',
			],
		] );
		$refresher = new LocalSiteRuntimeRefresher( $runner );

		$this->expectExceptionMessage( 'Local browser plugin runtime is inconsistent' );
		\ob_start();
		try {
			$refresher->refresh( $this->projectRoot, 'wordpress-container' );
		}
		finally {
			\ob_end_clean();
		}
	}

	public function testRefreshDeletesManagedPathsRemovedFromSource() :void {
		$manifest = $this->buildManifestForFixture();
		$manifest[ 'files' ][ 'flags/obsolete.flag' ] = [
			'sha256' => 'obsolete-hash',
			'size' => 0,
		];
		$manifest[ 'files' ][ 'vendor_prefixed/autoload.php' ] = [
			'sha256' => 'prefixed-obsolete-hash',
			'size' => 0,
		];
		$staleAutoloadFile = 'plugin_'.'autoload.php';
		$manifest[ 'files' ][ $staleAutoloadFile ] = [
			'sha256' => 'stale-autoload-hash',
			'size' => 0,
		];

		$runner = new ScriptedProcessRunner( [
			[
				'exit_code' => 0,
				'stdout' => \json_encode( [
					'manifest_exists' => true,
					'sentinels' => [
						'icwp-wpsf.php' => true,
						'plugin.json' => true,
					],
					'all_required_sentinels_present' => true,
					'has_any_required_sentinel' => true,
				], \JSON_UNESCAPED_SLASHES ) ?: '',
			],
			[
				'exit_code' => 0,
				'stdout' => \json_encode( $manifest, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES ) ?: '',
			],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
		] );
		$refresher = new LocalSiteRuntimeRefresher( $runner );

		\ob_start();
		try {
			$refresher->refresh( $this->projectRoot, 'wordpress-container' );
		}
		finally {
			\ob_end_clean();
		}

		$this->assertCount( 7, $runner->calls );
		$deleteList = (string)\file_get_contents( Path::join( $this->runtimeWorkspace(), 'deleted-managed-paths.json' ) );
		$this->assertStringContainsString( 'flags/obsolete.flag', $deleteList );
		$this->assertStringContainsString( 'vendor_prefixed/autoload.php', $deleteList );
		$this->assertStringContainsString( $staleAutoloadFile, $deleteList );
		$this->assertSame( 'docker', $runner->calls[ 2 ][ 'command' ][ 0 ] );
		$this->assertSame( 'cp', $runner->calls[ 2 ][ 'command' ][ 1 ] );
		$this->assertSame(
			'wordpress-container:/tmp/shield-browser-runtime-deletes.json',
			$runner->calls[ 2 ][ 'command' ][ 3 ]
		);
	}

	public function testRefreshReportsDeletePhaseFailuresExplicitly() :void {
		$manifest = $this->buildManifestForFixture();
		$manifest[ 'files' ][ 'flags/obsolete.flag' ] = [
			'sha256' => 'obsolete-hash',
			'size' => 0,
		];

		$runner = new ScriptedProcessRunner( [
			[
				'exit_code' => 0,
				'stdout' => \json_encode( [
					'manifest_exists' => true,
					'sentinels' => [
						'icwp-wpsf.php' => true,
						'plugin.json' => true,
					],
					'all_required_sentinels_present' => true,
					'has_any_required_sentinel' => true,
				], \JSON_UNESCAPED_SLASHES ) ?: '',
			],
			[
				'exit_code' => 0,
				'stdout' => \json_encode( $manifest, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES ) ?: '',
			],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 1, 'stderr' => 'failed to remove file: /var/www/html/wp-content/plugins/wp-simple-firewall/flags/obsolete.flag' ],
		] );
		$refresher = new LocalSiteRuntimeRefresher( $runner );

		$this->expectExceptionMessage( 'Local browser runtime refresh phase delete failed' );
		\ob_start();
		try {
			$refresher->refresh( $this->projectRoot, 'wordpress-container' );
		}
		finally {
			\ob_end_clean();
		}
	}

	/**
	 * @return array{schema_version:int,generated_at_unix:int,files:array<string,array{sha256:string,size:int}>}
	 */
	private function buildManifestForFixture() :array {
		$files = [];
		$paths = [
			'icwp-wpsf.php',
			'plugin.json',
			'plugin_compatibility.php',
			'plugin_init.php',
			'unsupported.php',
			'src',
			'templates',
			'languages',
			'vendor',
			'assets/dist',
			'assets/images',
			'flags',
			'tests/Helpers/RuntimeTestState.php',
			'tests/Helpers/TestDataFactory.php',
			'tests/Helpers/BrowserFixtureRegistry.php',
			'tests/Helpers/ActionRouter',
			'tests/Helpers/CrossSite',
			'tests/browser/support/shield-browser-fixtures.php',
		];
		foreach ( $paths as $path ) {
			$absolutePath = Path::join( $this->projectRoot, $path );
			if ( !\file_exists( $absolutePath ) ) {
				continue;
			}
			if ( \is_file( $absolutePath ) ) {
				$files[ \str_replace( '\\', '/', $path ) ] = $this->describeFile( $absolutePath );
				continue;
			}

			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $absolutePath, \FilesystemIterator::SKIP_DOTS )
			);
			foreach ( $iterator as $fileInfo ) {
				if ( !$fileInfo->isFile() ) {
					continue;
				}
				$relativePath = \str_replace( '\\', '/', Path::makeRelative( $fileInfo->getPathname(), $this->projectRoot ) );
				$files[ $relativePath ] = $this->describeFile( $fileInfo->getPathname() );
			}
		}

		\ksort( $files );

		return [
			'schema_version' => 1,
			'generated_at_unix' => 1,
			'files' => $files,
		];
	}

	private function runtimeWorkspace() :string {
		return Path::join( $this->projectRoot, 'tmp/.browser-runtime-refresh', \substr( \sha1( 'wordpress-container' ), 0, 12 ) );
	}

	/**
	 * @return array<int,array{exit_code:int,stdout?:string,stderr?:string}>
	 */
	private function emptyRuntimeSeedResponses() :array {
		return [
			[
				'exit_code' => 0,
				'stdout' => \json_encode( [
					'manifest_exists' => false,
					'sentinels' => [
						'icwp-wpsf.php' => false,
						'plugin.json' => false,
					],
					'all_required_sentinels_present' => false,
					'has_any_required_sentinel' => false,
				], \JSON_UNESCAPED_SLASHES ) ?: '',
			],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
		];
	}

	/**
	 * @return array{sha256:string,size:int}
	 */
	private function describeFile( string $filePath ) :array {
		$hash = \hash_file( 'sha256', $filePath );
		$this->assertIsString( $hash );

		return [
			'sha256' => $hash,
			'size' => (int)\filesize( $filePath ),
		];
	}

	private function seedRuntimeFiles( string $rootDir ) :void {
		$dirs = [
			'vendor',
			'assets/dist',
			'assets/images',
			'flags',
			'src',
			'templates/twig',
			'languages',
			'tests/Helpers/ActionRouter',
			'tests/Helpers/CrossSite',
			'tests/browser/support',
		];
		foreach ( $dirs as $dir ) {
			\mkdir( Path::join( $rootDir, $dir ), 0777, true );
		}

		\file_put_contents( Path::join( $rootDir, 'vendor', 'autoload.php' ), '<?php' );
		\file_put_contents( Path::join( $rootDir, 'plugin.json' ), '{}' );
		\file_put_contents( Path::join( $rootDir, 'icwp-wpsf.php' ), '<?php' );
		\file_put_contents( Path::join( $rootDir, 'plugin_compatibility.php' ), '<?php' );
		\file_put_contents( Path::join( $rootDir, 'plugin_init.php' ), '<?php' );
		\file_put_contents( Path::join( $rootDir, 'unsupported.php' ), '<?php' );
		\file_put_contents( Path::join( $rootDir, 'src', 'Example.php' ), '<?php' );
		\file_put_contents( Path::join( $rootDir, 'templates', 'twig', 'example.twig' ), 'twig' );
		\file_put_contents( Path::join( $rootDir, 'languages', 'wp-simple-firewall.pot' ), 'pot' );
		\file_put_contents( Path::join( $rootDir, 'assets', 'dist', 'shield-example.bundle.js' ), 'bundle' );
		\file_put_contents( Path::join( $rootDir, 'assets', 'images', 'logo.png' ), 'png' );
		\file_put_contents( Path::join( $rootDir, 'flags', 'index.html' ), 'index' );
		\file_put_contents( Path::join( $rootDir, 'tests', 'Helpers', 'RuntimeTestState.php' ), '<?php' );
		\file_put_contents( Path::join( $rootDir, 'tests', 'Helpers', 'TestDataFactory.php' ), '<?php' );
		\file_put_contents( Path::join( $rootDir, 'tests', 'Helpers', 'BrowserFixtureRegistry.php' ), '<?php' );
		\file_put_contents( Path::join( $rootDir, 'tests', 'Helpers', 'ActionRouter', 'ActionsQueueFixtureBuilder.php' ), '<?php' );
		\file_put_contents( Path::join( $rootDir, 'tests', 'Helpers', 'CrossSite', 'CrossSiteRuntime.php' ), '<?php' );
		\file_put_contents( Path::join( $rootDir, 'tests', 'browser', 'support', 'shield-browser-fixtures.php' ), '<?php' );
	}
}
