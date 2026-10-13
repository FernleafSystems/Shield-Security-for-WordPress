<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching\EarlyLoading;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading\StubBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Runs the generated stub as PHP's auto_prepend_file in a separate PHP process.
 */
class StubBuilderTest extends TestCase {

	use TempDirLifecycleTrait;

	private string $dir;

	protected function setUp() :void {
		parent::setUp();
		$this->dir = $this->createTrackedTempDir( 'shield-early-stub-' );
		\file_put_contents( $this->dir.'/main.php', '<?php echo "main|";' );
		\file_put_contents( $this->dir.'/bootstrap.php', '<?php echo "bootstrap:".SHIELD_EARLY_RULESET."|";' );
		\file_put_contents( $this->dir.'/host.php', '<?php echo "host|";' );
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testHostPrependRunsBeforeBootstrapAndThenTheRequestedScript() :void {
		$stub = $this->writeStub( $this->dir.'/bootstrap.php', $this->dir.'/host.php' );

		$this->assertSame( 'host|bootstrap:/rules.php|main|', $this->runPhp( $stub, $this->dir.'/main.php' ) );
	}

	public function testMissingBootstrapAndHostPrependAreSkipped() :void {
		$stub = $this->writeStub( $this->dir.'/deleted-plugin/bootstrap.php', $this->dir.'/gone.php' );

		$this->assertSame( 'main|', $this->runPhp( $stub, $this->dir.'/main.php' ) );
	}

	public function testStubRunsOnceWhenIncludedAgain() :void {
		$stub = $this->writeStub( $this->dir.'/bootstrap.php', null );
		\file_put_contents( $this->dir.'/main.php', sprintf( '<?php include %s; echo "main|";', \var_export( $stub, true ) ) );

		$this->assertSame( 'bootstrap:/rules.php|main|', $this->runPhp( $stub, $this->dir.'/main.php' ) );
	}

	public function testRequestingTheStubDirectlyPrintsOnlyTheMarker() :void {
		$stub = $this->writeStub( $this->dir.'/deleted-plugin/bootstrap.php', null );

		$this->assertSame( StubBuilder::SMOKE_MARKER, $this->runPhp( null, $stub ) );
	}

	public function testLoadedConstantHoldsTheStubsOwnPath() :void {
		\file_put_contents( $this->dir.'/bootstrap.php', '<?php echo SHIELD_EARLY_LOADED, "|";' );
		$stub = $this->writeStub( $this->dir.'/bootstrap.php', null );

		$this->assertSame( \realpath( $stub ).'|main|', $this->runPhp( $stub, $this->dir.'/main.php' ) );
	}

	public function testHalfWrittenBootstrapDoesNotStopTheRequest() :void {
		\file_put_contents( $this->dir.'/bootstrap.php', '<?php echo "partial' );
		$stub = $this->writeStub( $this->dir.'/bootstrap.php', null );

		$this->assertSame( 'main|', $this->runPhp( $stub, $this->dir.'/main.php' ) );
	}

	public function testPathsWithQuotesAreExportedSafely() :void {
		$odd = $this->createTrackedTempDir( "shield-o'brien-" );
		\file_put_contents( $odd.'/bootstrap.php', '<?php echo "odd|";' );
		$stub = $this->writeStub( $odd.'/bootstrap.php', null );

		$this->assertSame( 'odd|main|', $this->runPhp( $stub, $this->dir.'/main.php' ) );
	}

	private function writeStub( string $bootstrap, ?string $host ) :string {
		$path = $this->dir.'/'.StubBuilder::FILE_NAME;
		\file_put_contents( $path, StubBuilder::build( $bootstrap, '/rules.php', $host, [ 'root_dirs' => [ $this->dir ] ] ) );
		return $path;
	}

	private function runPhp( ?string $prepend, string $script ) :string {
		$command = [ \PHP_BINARY, '-n', '-d', 'display_errors=stderr' ];
		if ( $prepend !== null ) {
			\array_push( $command, '-d', 'auto_prepend_file='.$prepend );
		}
		$command[] = $script;

		$process = \proc_open( $command, [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
		$this->assertIsResource( $process );
		$stdout = (string)\stream_get_contents( $pipes[ 1 ] );
		$stderr = (string)\stream_get_contents( $pipes[ 2 ] );
		\fclose( $pipes[ 1 ] );
		\fclose( $pipes[ 2 ] );
		$this->assertSame( 0, \proc_close( $process ), $stderr );
		$this->assertSame( '', $stderr );
		return $stdout;
	}
}
