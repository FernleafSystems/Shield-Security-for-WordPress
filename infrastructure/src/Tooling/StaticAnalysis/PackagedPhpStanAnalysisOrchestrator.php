<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Process\Process;

class PackagedPhpStanAnalysisOrchestrator {

	private ProcessRunner $processRunner;

	private PackagedPhpStanOutcomeClassifier $classifier;

	public function __construct(
		?ProcessRunner $processRunner = null,
		?PackagedPhpStanOutcomeClassifier $classifier = null
	) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->classifier = $classifier ?? new PackagedPhpStanOutcomeClassifier();
	}

	/**
	 * @return string[]
	 */
	public function buildDockerCommand(
		string $projectRoot,
		string $composerImage,
		string $packageDirRelative
	) :array {
		$packageContainerPath = '/app/'.$this->normalizeRelativePath( $packageDirRelative );

		return [
			'docker',
			'run',
			'--rm',
			'--name',
			'shield-phpstan-package',
			'-v',
			$projectRoot.':/app',
			'-w',
			'/app',
			'-e',
			'SHIELD_PACKAGE_PATH='.$packageContainerPath,
			$composerImage,
			'php',
			'/app/vendor/phpstan/phpstan/phpstan',
			'analyse',
			'-c',
			'/app/phpstan.package.neon.dist',
			'--error-format=json',
			'--no-progress',
			'--memory-limit=1G',
		];
	}

	public function runCommand( array $command, string $workingDir, ?callable $onOutput = null ) :PackagedPhpStanOutcome {
		$process = $this->processRunner->run( $command, $workingDir, $onOutput );
		$combinedOutput = $this->buildClassifierInput( $process );
		return $this->classifier->classify( $process->getExitCode() ?? 1, $combinedOutput );
	}

	private function buildClassifierInput( Process $process ) :string {
		return $process->getOutput()."\n".$process->getErrorOutput();
	}

	private function normalizeRelativePath( string $path ) :string {
		return \trim( \str_replace( '\\', '/', $path ), '/' );
	}
}
