<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Process\Process;

class PackagedPhpStanAnalysisOrchestrator {

	private const PACKAGE_CONFIG_RELATIVE_PATH = 'phpstan.package.neon.dist';
	private const PACKAGE_BOOTSTRAP_RELATIVE_PATH = 'tests/stubs/phpstan-package-bootstrap.php';
	private const PACKAGE_VENDOR_AUTOLOAD_RELATIVE_PATH = 'vendor/autoload.php';
	private const PACKAGE_VENDOR_PREFIXED_AUTOLOAD_RELATIVE_PATH = 'vendor_prefixed/autoload.php';

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
		$packageContainerPath = $this->buildPackageContainerPath( $packageDirRelative );

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

	public function buildPackageContainerPath( string $packageDirRelative ) :string {
		return '/app/'.$this->normalizeRelativePath( $packageDirRelative );
	}

	public function assertPreflight( string $projectRoot, string $packageDir ) :void {
		$this->assertFileExists(
			$projectRoot.'/'.self::PACKAGE_CONFIG_RELATIVE_PATH,
			'ERROR: Missing '.self::PACKAGE_CONFIG_RELATIVE_PATH.' at project root'
		);
		$this->assertFileExists(
			$projectRoot.'/'.self::PACKAGE_BOOTSTRAP_RELATIVE_PATH,
			'ERROR: Missing '.self::PACKAGE_BOOTSTRAP_RELATIVE_PATH
		);
		$this->assertFileExists(
			$packageDir.'/'.self::PACKAGE_VENDOR_AUTOLOAD_RELATIVE_PATH,
			'ERROR: Packaged vendor autoload not found: '.$packageDir.'/'.self::PACKAGE_VENDOR_AUTOLOAD_RELATIVE_PATH
		);
		$this->assertFileExists(
			$packageDir.'/'.self::PACKAGE_VENDOR_PREFIXED_AUTOLOAD_RELATIVE_PATH,
			'ERROR: Packaged vendor_prefixed autoload not found: '.$packageDir.'/'.self::PACKAGE_VENDOR_PREFIXED_AUTOLOAD_RELATIVE_PATH
		);
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

	private function assertFileExists( string $path, string $message ) :void {
		if ( !\is_file( $path ) ) {
			throw new \RuntimeException( $message );
		}
	}
}
