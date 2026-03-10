<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis\PhpSyntaxLinter;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use PHPUnit\Framework\TestCase;

class PhpSyntaxLinterTest extends TestCase {

	use TempDirLifecycleTrait;

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = $this->createTrackedTempDir( 'shield-linter-' );
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testLintFindsPhpFilesIncludingShebangScripts() :void {
		$this->writeFile( 'bin/tool', "#!/usr/bin/env php\n<?php echo 'ok';\n" );
		$this->writeFile( 'infrastructure/src/Tool.php', "<?php\nclass Tool {}\n" );
		$this->writeFile( 'tests/Unit/ExampleTest.php', "<?php\nclass ExampleTest {}\n" );
		$this->writeFile( 'bin/not-php', "#!/usr/bin/env bash\necho ok\n" );

		$report = ( new PhpSyntaxLinter() )->lint(
			$this->projectRoot,
			[ 'bin', 'infrastructure/src', 'tests' ]
		);

		$this->assertSame( 3, $report->getCheckedFileCount() );
		$this->assertFalse( $report->hasFailures() );
	}

	public function testLintReportsSyntaxFailures() :void {
		$this->writeFile( 'bin/good.php', "<?php\n\$value = 1;\n" );
		$this->writeFile( 'tests/Unit/BadTest.php', "<?php\nfunction broken( {\n" );

		$report = ( new PhpSyntaxLinter() )->lint(
			$this->projectRoot,
			[ 'bin', 'tests' ]
		);

		$this->assertSame( 2, $report->getCheckedFileCount() );
		$this->assertTrue( $report->hasFailures() );
		$this->assertCount( 1, $report->getFailures() );
		$this->assertSame( 'tests/Unit/BadTest.php', $report->getFailures()[ 0 ][ 'path' ] );
		$this->assertStringContainsString( 'Parse error', $report->getFailures()[ 0 ][ 'output' ] );
	}

	private function writeFile( string $relativePath, string $contents ) :void {
		$path = $this->projectRoot.DIRECTORY_SEPARATOR.\str_replace( '/', DIRECTORY_SEPARATOR, $relativePath );
		$dir = \dirname( $path );
		if ( !\is_dir( $dir ) ) {
			\mkdir( $dir, 0777, true );
		}

		\file_put_contents( $path, $contents );
	}
}
