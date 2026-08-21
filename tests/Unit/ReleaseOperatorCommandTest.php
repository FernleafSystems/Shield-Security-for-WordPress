<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\ReleaseOperatorCommand;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingProcessRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Path;

class ReleaseOperatorCommandTest extends BaseUnitTest {

	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testInteractiveMenuRoutesBuildZipAction() :void {
		$root = $this->projectRoot();
		$runner = new RecordingProcessRunner( [ 0 ] );

		$exitCode = $this->execute( new ReleaseOperatorCommand( 'operator', null, $root, $runner ), [ 'Build ZIP', 'y' ] );

		$this->assertSame( Command::SUCCESS, $exitCode );
		$this->assertSame( [ 'composer', 'build-zip' ], $runner->calls[ 0 ][ 'command' ] );
	}

	public function testInteractiveMenuRoutesPackageSvnAction() :void {
		$root = $this->projectRoot();
		$target = $this->createTrackedTempDir( 'shield-svn-target-' );
		$runner = new RecordingProcessRunner( [ 0 ] );

		$exitCode = $this->execute( new ReleaseOperatorCommand( 'operator', null, $root, $runner ), [ 'Package for SVN', $target, 'y' ] );

		$this->assertSame( Command::SUCCESS, $exitCode );
		$this->assertSame( [ 'composer', 'package-plugin', '--', '--output='.Path::normalize( (string)\realpath( $target ) ) ], $runner->calls[ 0 ][ 'command' ] );
	}

	public function testInteractiveMenuRoutesPrepareReleaseAction() :void {
		$root = $this->projectRoot();
		$runner = new RecordingProcessRunner( [ 0 ] );

		$exitCode = $this->execute( new ReleaseOperatorCommand( 'operator', null, $root, $runner ), [ 'Prepare release', '23.4.5', '2026040506', 'auto', 'y' ] );

		$this->assertSame( Command::SUCCESS, $exitCode );
		$this->assertSame( [
			PHP_BINARY,
			'bin/prepare-release.php',
			'--version=23.4.5',
			'--release-timestamp=2026040506',
			'--build=auto',
		], $runner->calls[ 0 ][ 'command' ] );
	}

	public function testRejectsUnsupportedFixedAction() :void {
		$this->expectException( \InvalidArgumentException::class );
		new ReleaseOperatorCommand( 'operator:unknown', 'unknown', $this->projectRoot() );
	}

	public function testPackageSvnRejectsMissingTargetBeforeRunningProcess() :void {
		$root = $this->projectRoot();
		$runner = RecordingProcessRunner::strict( [] );
		$missing = $root.'/missing-svn-target';

		$exitCode = $this->execute( new ReleaseOperatorCommand( 'operator:package-svn', 'package-svn', $root, $runner ), [ $missing ] );

		$this->assertSame( Command::FAILURE, $exitCode );
		$this->assertSame( [], $runner->calls );
	}

	public function testPackageSvnCanonicalisesRelativeExternalTargetBeforeWritingStateAndRunning() :void {
		$root = $this->projectRoot();
		$rootSpelling = Path::join( $root, '.' );
		$target = $this->createTrackedTempDir( 'shield-svn-target-' );
		$relativeTarget = Path::makeRelative( $target, $root );
		$canonicalRoot = Path::normalize( (string)\realpath( $root ) );
		$canonicalTarget = Path::normalize( (string)\realpath( $target ) );
		$runner = new StateObservingProcessRunner( [ 0 ], $canonicalRoot.'/tmp/operator-state.json' );

		$exitCode = $this->execute(
			new ReleaseOperatorCommand( 'operator:package-svn', 'package-svn', $rootSpelling, $runner ),
			[ $relativeTarget, 'y' ]
		);

		$this->assertSame( Command::SUCCESS, $exitCode );
		$this->assertTrue( $runner->stateExistedWhenRunStarted );
		$this->assertSame( $canonicalRoot, $runner->calls[ 0 ][ 'working_dir' ] );
		$this->assertSame( [ 'composer', 'package-plugin', '--', '--output='.$canonicalTarget ], $runner->calls[ 0 ][ 'command' ] );
		$this->assertSame( $canonicalTarget, $this->readState( $canonicalRoot )[ 'inputs' ][ 'target' ] );
	}

	public function testPackageSvnRejectsProjectChildBeforeWritingStateOrRunning() :void {
		$root = $this->projectRoot();
		$target = Path::join( $root, 'svn-target' );
		mkdir( $target, 0777, true );
		$runner = RecordingProcessRunner::strict( [] );

		$exitCode = $this->execute( new ReleaseOperatorCommand( 'operator:package-svn', 'package-svn', $root, $runner ), [ $target ] );

		$this->assertSame( Command::FAILURE, $exitCode );
		$this->assertFileDoesNotExist( $root.'/tmp/operator-state.json' );
		$this->assertSame( [], $runner->calls );
	}

	public function testPrepareReleaseUsesConfiguredAndInteractiveDefaults() :void {
		$root = $this->projectRoot( '23.4.5' );
		$runner = new RecordingProcessRunner( [ 0 ] );
		$before = time();

		$exitCode = $this->execute(
			new ReleaseOperatorCommand( 'operator:prepare-release', 'prepare-release', $root, $runner ),
			[ '', '', '', 'y' ]
		);

		$this->assertSame( Command::SUCCESS, $exitCode );
		$state = $this->readState( $root );
		$this->assertSame( [
			'version' => '23.4.5',
			'release_timestamp' => $state[ 'inputs' ][ 'release_timestamp' ],
			'build' => 'auto',
		], $state[ 'inputs' ] );
		$this->assertGreaterThanOrEqual( $before, $state[ 'inputs' ][ 'release_timestamp' ] );
		$this->assertLessThanOrEqual( time(), $state[ 'inputs' ][ 'release_timestamp' ] );
		$this->assertSame( [
			PHP_BINARY,
			'bin/prepare-release.php',
			'--version=23.4.5',
			'--release-timestamp='.$state[ 'inputs' ][ 'release_timestamp' ],
			'--build=auto',
		], $runner->calls[ 0 ][ 'command' ] );
	}

	/**
	 * @dataProvider providerFixedActions
	 */
	public function testSuccessfulFixedActionsWriteStateBeforeRunning( string $action, array $answers, array $expectedCommand ) :void {
		$root = $this->projectRoot();
		$runner = new StateObservingProcessRunner( [ 0 ], $root.'/tmp/operator-state.json' );

		$exitCode = $this->execute( new ReleaseOperatorCommand( 'operator:'.$action, $action, $root, $runner ), $answers );

		$this->assertSame( Command::SUCCESS, $exitCode );
		$this->assertTrue( $runner->stateExistedWhenRunStarted );
		$this->assertSame( $expectedCommand, $runner->calls[ 0 ][ 'command' ] );
		$this->assertSame( $root, $runner->calls[ 0 ][ 'working_dir' ] );
		$state = $this->readState( $root );
		$this->assertSame( [ 'action', 'inputs', 'command' ], array_keys( $state ) );
		$this->assertSame( $action, $state[ 'action' ] );
		$this->assertSame( $expectedCommand, $state[ 'command' ] );
	}

	public function testNonzeroProcessExitIsReturnedAndStateRemainsAvailable() :void {
		$root = $this->projectRoot();
		$runner = new RecordingProcessRunner( [ 9 ] );

		$exitCode = $this->execute( new ReleaseOperatorCommand( 'operator:build-zip', 'build-zip', $root, $runner ), [ 'y' ] );

		$this->assertSame( 9, $exitCode );
		$this->assertFileExists( $root.'/tmp/operator-state.json' );
	}

	public function testDeclineDoesNotWriteStateOrRunProcess() :void {
		$root = $this->projectRoot();
		$runner = RecordingProcessRunner::strict( [] );

		$this->assertSame( Command::SUCCESS, $this->execute( new ReleaseOperatorCommand( 'operator:build-zip', 'build-zip', $root, $runner ), [ 'n' ] ) );
		$this->assertFileDoesNotExist( $root.'/tmp/operator-state.json' );
		$this->assertSame( [], $runner->calls );
	}

	public function testInteractiveCancelDoesNotWriteStateOrRunProcess() :void {
		$root = $this->projectRoot();
		$runner = RecordingProcessRunner::strict( [] );

		$this->assertSame( Command::SUCCESS, $this->execute( new ReleaseOperatorCommand( 'operator', null, $root, $runner ), [ 'cancel' ] ) );
		$this->assertFileDoesNotExist( $root.'/tmp/operator-state.json' );
		$this->assertSame( [], $runner->calls );
	}

	public function testStateWriteFailurePreventsProcessExecution() :void {
		$root = $this->projectRoot();
		file_put_contents( $root.'/tmp', 'not a directory' );
		$runner = RecordingProcessRunner::strict( [] );

		$this->assertSame( Command::FAILURE, $this->execute( new ReleaseOperatorCommand( 'operator:build-zip', 'build-zip', $root, $runner ), [ 'y' ] ) );
		$this->assertSame( [], $runner->calls );
	}

	/** @return array<string,array{string,array<int,string>,string[]}> */
	public function providerFixedActions() :array {
		$target = $this->createTrackedTempDir( 'shield-svn-target-' );
		$canonicalTarget = Path::normalize( (string)\realpath( $target ) );
		return [
			'package svn' => [ 'package-svn', [ $target, 'y' ], [ 'composer', 'package-plugin', '--', '--output='.$canonicalTarget ] ],
			'prepare release' => [ 'prepare-release', [ '21.1.2', '2026020401', '202602.0401', 'y' ], [ PHP_BINARY, 'bin/prepare-release.php', '--version=21.1.2', '--release-timestamp=2026020401', '--build=202602.0401' ] ],
			'build zip' => [ 'build-zip', [ 'y' ], [ 'composer', 'build-zip' ] ],
		];
	}

	private function execute( ReleaseOperatorCommand $command, array $answers ) :int {
		$application = new Application();
		$application->setAutoExit( false );
		$application->add( $command );
		$tester = new CommandTester( $application->find( $command->getName() ) );
		$tester->setInputs( $answers );
		$exitCode = $tester->execute( [] );
		return $exitCode;
	}

	private function projectRoot( string $version = '21.1.2' ) :string {
		$root = $this->createTrackedTempDir( 'shield-release-operator-' );
		mkdir( $root.'/plugin-spec', 0777, true );
		file_put_contents( $root.'/plugin-spec/01_properties.json', json_encode( [ 'version' => $version ] ) );
		return $root;
	}

	/** @return array<string,mixed> */
	private function readState( string $root ) :array {
		$state = json_decode( (string)file_get_contents( $root.'/tmp/operator-state.json' ), true );
		$this->assertIsArray( $state );
		return $state;
	}
}

class StateObservingProcessRunner extends RecordingProcessRunner {
	public bool $stateExistedWhenRunStarted = false;

	private string $statePath;

	public function __construct( array $exitCodes, string $statePath ) {
		parent::__construct( $exitCodes );
		$this->statePath = $statePath;
	}

	public function run( array $command, string $workingDir, ?callable $onOutput = null, ?array $envOverrides = null ) :Process {
		$this->stateExistedWhenRunStarted = is_file( $this->statePath );
		return parent::run( $command, $workingDir, $onOutput, $envOverrides );
	}
}
