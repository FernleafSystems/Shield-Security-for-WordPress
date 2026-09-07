<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\ReleaseOperatorCommand;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingProcessRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
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

	public function testPackageSvnRecallsTargetAcrossInvocations() :void {
		$root = $this->projectRoot();
		$target = $this->createTrackedTempDir( 'shield-svn-target-' );
		$runner = new RecordingProcessRunner( [ 0, 0 ] );

		foreach ( [ [ $target, 'y' ], [ '', 'y' ] ] as $answers ) {
			$this->assertSame( Command::SUCCESS, $this->execute(
				new ReleaseOperatorCommand( 'operator:package-svn', 'package-svn', $root, $runner ), $answers
			) );
		}
		$this->assertCount( 2, $runner->calls );
		foreach ( $runner->calls as $call ) {
			$this->assertSame( [ 'composer', 'package-plugin', '--', '--output='.Path::normalize( (string)realpath( $target ) ) ], $call[ 'command' ] );
		}
	}

	public function testExecutionDoesNotRequirePhpSelf() :void {
		$hadPhpSelf = \array_key_exists( 'PHP_SELF', $_SERVER );
		$originalPhpSelf = $_SERVER[ 'PHP_SELF' ] ?? null;
		unset( $_SERVER[ 'PHP_SELF' ] );

		try {
			$runner = new RecordingProcessRunner( [ 0 ] );
			$exitCode = $this->execute(
				new ReleaseOperatorCommand( 'operator:build-zip', 'build-zip', $this->projectRoot(), $runner ),
				[ 'y' ]
			);

			$this->assertSame( Command::SUCCESS, $exitCode );
			$this->assertSame( [ 'composer', 'build-zip' ], $runner->calls[ 0 ][ 'command' ] );
		}
		finally {
			if ( $hadPhpSelf ) {
				$_SERVER[ 'PHP_SELF' ] = $originalPhpSelf;
			}
			else {
				unset( $_SERVER[ 'PHP_SELF' ] );
			}
		}
	}

	public function testRememberedInputsSurviveOtherActionsAndOverrides() :void {
		$root = $this->projectRoot( '21.1.2' );
		$target = Path::normalize( (string)realpath( $this->createTrackedTempDir( 'shield-svn-target-' ) ) );
		$replacement = Path::normalize( (string)realpath( $this->createTrackedTempDir( 'shield-svn-replacement-' ) ) );
		$runner = new RecordingProcessRunner();
		$steps = [
			[ 'package-svn', [ $target, 'y' ], $target, null ],
			[ 'prepare-release', [ '23.4.5', '', '', 'y' ], $target, '23.4.5' ],
			[ 'build-zip', [ 'y' ], $target, '23.4.5' ],
			[ 'package-svn', [ '', 'y' ], $target, '23.4.5' ],
			[ 'prepare-release', [ '', '', '', 'y' ], $target, '23.4.5' ],
			[ 'package-svn', [ $replacement, 'y' ], $replacement, '23.4.5' ],
			[ 'prepare-release', [ '24.0.1', '', '', 'y' ], $replacement, '24.0.1' ],
			[ 'package-svn', [ '', 'y' ], $replacement, '24.0.1' ],
			[ 'prepare-release', [ '', '', '', 'y' ], $replacement, '24.0.1' ],
		];
		foreach ( $steps as $index => [ $action, $answers, $expectedTarget, $expectedVersion ] ) {
			$before = time();
			$this->assertSame( Command::SUCCESS, $this->execute(
				new ReleaseOperatorCommand( 'operator', null, $root, $runner ), array_merge( [ $action ], $answers )
			) );
			$this->assertCount( $index + 1, $runner->calls );
			$state = $this->readState( $root );
			$this->assertSame( $expectedTarget, $state[ 'inputs' ][ 'target' ] );
			$this->assertSame( $expectedVersion, $state[ 'inputs' ][ 'version' ] ?? null );
			$this->assertSame( $action, $state[ 'action' ] );
			if ( $action === 'prepare-release' ) {
				$this->assertGreaterThanOrEqual( $before, $state[ 'inputs' ][ 'release_timestamp' ] );
				$this->assertLessThanOrEqual( time(), $state[ 'inputs' ][ 'release_timestamp' ] );
				$expected = [ PHP_BINARY, 'bin/prepare-release.php', '--version='.$expectedVersion,
					'--release-timestamp='.$state[ 'inputs' ][ 'release_timestamp' ], '--build=auto' ];
			}
			else {
				$this->assertArrayNotHasKey( 'release_timestamp', $state[ 'inputs' ] );
				$this->assertArrayNotHasKey( 'build', $state[ 'inputs' ] );
				$expected = $action === 'package-svn'
					? [ 'composer', 'package-plugin', '--', '--output='.$expectedTarget ] : [ 'composer', 'build-zip' ];
			}
			$this->assertSame( $expected, $runner->calls[ $index ][ 'command' ] );
			$this->assertSame( $expected, $state[ 'command' ] );
		}
	}

	public function testExistingStateUsesVersionButNotOldTimestampOrBuild() :void {
		$root = $this->projectRoot();
		mkdir( $root.'/tmp' );
		file_put_contents( $root.'/tmp/operator-state.json', json_encode( [
			'action' => 'prepare-release', 'inputs' => [ 'version' => '24.1.0', 'release_timestamp' => 1, 'build' => 'old' ],
			'command' => [ 'unused' ],
		] ) );
		$runner = new RecordingProcessRunner();
		$before = time();
		$this->assertSame( Command::SUCCESS, $this->execute(
			new ReleaseOperatorCommand( 'operator:prepare-release', 'prepare-release', $root, $runner ), [ '', '', '', 'y' ]
		) );
		$this->assertCount( 1, $runner->calls );
		$timestamp = $this->readState( $root )[ 'inputs' ][ 'release_timestamp' ];
		$this->assertGreaterThanOrEqual( $before, $timestamp );
		$this->assertLessThanOrEqual( time(), $timestamp );
		$this->assertSame( [ PHP_BINARY, 'bin/prepare-release.php', '--version=24.1.0', '--release-timestamp='.$timestamp, '--build=auto' ], $runner->calls[ 0 ][ 'command' ] );
	}

	/** @dataProvider providerInvalidRememberedState */
	public function testInvalidRememberedStateAllowsConfiguredDefaults( string $contents ) :void {
		$root = $this->projectRoot( '25.1.0' );
		mkdir( $root.'/tmp' );
		file_put_contents( $root.'/tmp/operator-state.json', $contents );
		$runner = new RecordingProcessRunner();
		$this->assertSame( Command::SUCCESS, $this->execute(
			new ReleaseOperatorCommand( 'operator:prepare-release', 'prepare-release', $root, $runner ), [ '', '', '', 'y' ]
		) );
		$this->assertCount( 1, $runner->calls );
		$this->assertSame( '--version=25.1.0', $runner->calls[ 0 ][ 'command' ][ 2 ] );
	}

	public function providerInvalidRememberedState() :array {
		return [
			'invalid JSON' => [ '{' ],
			'scalar root' => [ 'false' ],
			'scalar inputs' => [ '{"inputs":42}' ],
			'array version' => [ '{"inputs":{"version":[]}}' ],
			'numeric version' => [ '{"inputs":{"version":123}}' ],
			'boolean version' => [ '{"inputs":{"version":true}}' ],
			'blank version' => [ '{"inputs":{"version":"  "}}' ],
		];
	}

	public function testExistingTargetSurvivesInvalidVersionAndFailedProcess() :void {
		$root = $this->projectRoot();
		$target = Path::normalize( (string)realpath( $this->createTrackedTempDir( 'shield-svn-target-' ) ) );
		mkdir( $root.'/tmp' );
		file_put_contents( $root.'/tmp/operator-state.json', json_encode( [
			'action' => 'package-svn', 'inputs' => [ 'target' => $target, 'version' => [] ], 'command' => [ 'unused' ],
		] ) );
		$runner = new RecordingProcessRunner( [ 9, 0 ] );
		foreach ( [ 9, 0 ] as $expectedExit ) {
			$this->assertSame( $expectedExit, $this->execute(
				new ReleaseOperatorCommand( 'operator:package-svn', 'package-svn', $root, $runner ), [ '', 'y' ]
			) );
		}
		$this->assertCount( 2, $runner->calls );
		foreach ( $runner->calls as $call ) {
			$this->assertSame( [ 'composer', 'package-plugin', '--', '--output='.$target ], $call[ 'command' ] );
		}
		$this->assertSame( [ 'target' => $target ], $this->readState( $root )[ 'inputs' ] );
	}

	/** @dataProvider providerInvalidSavedTarget */
	public function testSavedTargetUsesExistingValidationAndRetry( string $kind ) :void {
		$root = $this->projectRoot();
		$target = $kind === 'missing' ? $root.'/missing' : $root;
		$replacement = Path::normalize( (string)realpath( $this->createTrackedTempDir( 'shield-svn-target-' ) ) );
		mkdir( $root.'/tmp' );
		$contents = json_encode( [ 'inputs' => [ 'target' => $target ] ] );
		file_put_contents( $root.'/tmp/operator-state.json', $contents );
		$runner = RecordingProcessRunner::strict( [] );
		$this->assertSame( Command::FAILURE, $this->execute(
			new ReleaseOperatorCommand( 'operator:package-svn', 'package-svn', $root, $runner ), [ '' ]
		) );
		$this->assertSame( [], $runner->calls );
		$this->assertSame( $contents, file_get_contents( $root.'/tmp/operator-state.json' ) );
		$runner = new RecordingProcessRunner();
		$this->assertSame( Command::SUCCESS, $this->execute(
			new ReleaseOperatorCommand( 'operator:package-svn', 'package-svn', $root, $runner ), [ '', $replacement, 'y' ]
		) );
		$this->assertCount( 1, $runner->calls );
		$this->assertSame( [ 'composer', 'package-plugin', '--', '--output='.$replacement ], $runner->calls[ 0 ][ 'command' ] );
		$this->assertSame( $replacement, $this->readState( $root )[ 'inputs' ][ 'target' ] );
	}

	public function providerInvalidSavedTarget() :array {
		return [ 'missing' => [ 'missing' ], 'internal' => [ 'internal' ] ];
	}

	public function testDeclinedReplacementAndMenuCancelPreserveExistingState() :void {
		$root = $this->projectRoot();
		mkdir( $root.'/tmp' );
		$contents = json_encode( [ 'inputs' => [ 'version' => '23.4.5' ] ] );
		file_put_contents( $root.'/tmp/operator-state.json', $contents );
		$runner = RecordingProcessRunner::strict( [] );
		$this->assertSame( Command::SUCCESS, $this->execute(
			new ReleaseOperatorCommand( 'operator:prepare-release', 'prepare-release', $root, $runner ), [ '24.0.0', '', '', 'n' ]
		) );
		$this->assertSame( $contents, file_get_contents( $root.'/tmp/operator-state.json' ) );
		$this->assertSame( Command::SUCCESS, $this->execute(
			new ReleaseOperatorCommand( 'operator', null, $root, $runner ), [ 'cancel' ]
		) );
		$this->assertSame( $contents, file_get_contents( $root.'/tmp/operator-state.json' ) );
		$this->assertSame( [], $runner->calls );
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
		$command->setHelperSet( new HelperSet( [ new QuestionHelper() ] ) );
		$tester = new CommandTester( $command );
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
