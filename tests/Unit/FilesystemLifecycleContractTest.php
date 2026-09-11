<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\{
	FilesystemFixturePolicy,
	PluginPathsTrait,
	TempDirLifecycleTrait
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ScriptCommandTestTrait;

class FilesystemLifecycleContractTest extends BaseUnitTest {

	use PluginPathsTrait;
	use ScriptCommandTestTrait;
	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	/**
	 * @dataProvider unsafeFixtureProvider
	 */
	public function testPolicyDetectsUnsafeFixturePattern( string $body ) :void {
		$violations = ( new FilesystemFixturePolicy() )->scanSource( "<?php\n".$body );

		$this->assertCount( 1, $violations );
		$this->assertSame( 2, $violations[ 0 ][ 'line' ] );
	}

	public static function unsafeFixtureProvider() :array {
		return [
			'direct tempnam' => [ 'tempnam( sys_get_temp_dir(), "shield-" );' ],
			'multiline tempnam' => [ "tempnam(\n\tsys_get_temp_dir(),\n\t\"shield-\"\n);" ],
			'direct uniqid concatenation' => [ '$path = sys_get_temp_dir()."/shield-".uniqid();' ],
			'comma-bearing nested entropy' => [ '$path = sys_get_temp_dir().sprintf( "%s-%s", "shield", uniqid() );' ],
			'nested random bytes' => [ '$path = sys_get_temp_dir()."/shield-".bin2hex( random_bytes( 6 ) );' ],
			'path join entropy' => [ '$path = Path::join( sys_get_temp_dir(), "shield-".uniqid() );' ],
			'direct mkdir' => [ 'mkdir( sys_get_temp_dir()."/fixture", 0777, true );' ],
			'direct touch' => [ 'touch( sys_get_temp_dir()."/fixture" );' ],
			'direct file write' => [ 'file_put_contents( sys_get_temp_dir()."/fixture", "data" );' ],
			'overlapping path and writer violation' => [
				'mkdir( sys_get_temp_dir()."/fixture-".uniqid(), 0777, true );',
			],
			'direct copy destination' => [ 'copy( "/safe/source", sys_get_temp_dir()."/copy" );' ],
			'direct rename destination' => [ 'rename( "/safe/source", sys_get_temp_dir()."/renamed" );' ],
			'writing fopen' => [ 'fopen( sys_get_temp_dir()."/fixture", "wb" );' ],
			'dynamic fopen mode' => [ 'fopen( sys_get_temp_dir()."/fixture", $mode );' ],
		];
	}

	/**
	 * @dataProvider allowedFixtureProvider
	 */
	public function testPolicyAllowsNonFixturePattern( string $body ) :void {
		$this->assertSame( [], ( new FilesystemFixturePolicy() )->scanSource( "<?php\n".$body ) );
	}

	public static function allowedFixtureProvider() :array {
		return [
			'existing root inspection' => [ '$root = sys_get_temp_dir(); $exists = is_dir( $root );' ],
			'comment' => [ '// tempnam( sys_get_temp_dir(), "ignored-" );' ],
			'string example' => [ '$example = "sys_get_temp_dir().uniqid()";' ],
			'separate array entries' => [ '$parts = [ sys_get_temp_dir(), uniqid() ];' ],
			'separate function arguments' => [ 'inspect( sys_get_temp_dir(), "id-".uniqid() );' ],
			'comparison' => [ '$same = sys_get_temp_dir()."/root" === "id-".uniqid();' ],
			'arithmetic' => [ '$value = wrap( sys_get_temp_dir()."/root" ) + wrap( "id-".uniqid() );' ],
			'coalescing' => [ '$value = sys_get_temp_dir()."/root" ?? "id-".uniqid();' ],
			'logical' => [ '$value = sys_get_temp_dir()."/root" AND "id-".uniqid();' ],
			'path join and entropy in separate arguments' => [
				'inspect( Path::join( sys_get_temp_dir(), "root" ), uniqid() );',
			],
			'read-only fopen' => [ 'fopen( sys_get_temp_dir()."/fixture", "rb" );' ],
			'method lookalike' => [ '$factory->tempnam( sys_get_temp_dir(), "ignored-" );' ],
			'system-temp method lookalike' => [
				'mkdir( $factory->sys_get_temp_dir()."/fixture", 0777, true );',
			],
			'namespaced lookalike' => [ 'Vendor\\tempnam( sys_get_temp_dir(), "ignored-" );' ],
			'constructor lookalike' => [ 'new tempnam( sys_get_temp_dir() );' ],
		];
	}

	public function testPolicyHasOneDocumentedLifecycleTraitTestException() :void {
		$source = '<?php $path = tempnam( sys_get_temp_dir(), "shield-" );';
		$file = '/project/tests/Unit/Helpers/TempDirLifecycleTraitTest.php';

		$this->assertSame( [], ( new FilesystemFixturePolicy() )->scanSource( $source, $file ) );
	}

	public function testCliReportsFileLineRemediationAndFailureStatus() :void {
		$this->skipIfPackageScriptUnavailable();
		$fixture = $this->createTrackedTempFile(
			'shield-fixture-policy-',
			'.php',
			"<?php\n\$path = tempnam( sys_get_temp_dir(), 'shield-' );\n"
		);

		$process = $this->runPhpScript( 'bin/check-unit-test-fixtures.php', [ $fixture ] );
		$output = $this->processOutput( $process );

		$this->assertSame( 1, $process->getExitCode() ?? 0 );
		$this->assertStringContainsString( \basename( $fixture ).':2:', $output );
		$this->assertStringContainsString( 'Remediation:', $output );
		$this->assertStringContainsString( 'createTrackedTempFile()', $output );
	}
}
