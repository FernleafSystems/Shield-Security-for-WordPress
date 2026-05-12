<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis\PhpSyntaxLinter;
use Symfony\Component\Filesystem\Path;

class ToolingAnalysisLane {

	private const LINT_PATHS = [
		'bin',
		'infrastructure/src',
		'tests',
	];

	private ProcessRunner $processRunner;

	private PhpSyntaxLinter $syntaxLinter;

	public function __construct(
		?ProcessRunner $processRunner = null,
		?PhpSyntaxLinter $syntaxLinter = null
	) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->syntaxLinter = $syntaxLinter ?? new PhpSyntaxLinter( $this->processRunner );
	}

	public function run( string $rootDir ) :int {
		echo 'Mode: analyze-tooling'.\PHP_EOL;
		echo 'Running PHP syntax lint for tooling and tests.'.\PHP_EOL;

		$report = $this->syntaxLinter->lint( $rootDir, self::LINT_PATHS );
		echo 'PHP syntax lint checked '.$report->getCheckedFileCount().' file(s).'.\PHP_EOL;

		if ( $report->hasFailures() ) {
			echo 'Tooling syntax lint failed.'.\PHP_EOL;
			foreach ( $report->getFailures() as $failure ) {
				echo '- '.$failure[ 'path' ].\PHP_EOL;
				echo $failure[ 'output' ].\PHP_EOL;
			}
			return 1;
		}

		echo 'Running PHPStan for tooling and test support.'.\PHP_EOL;
		return $this->processRunner->runForExitCode(
			[
				\PHP_BINARY,
				Path::join( '.', 'vendor', 'phpstan', 'phpstan', 'phpstan' ),
				'analyse',
				'-c',
				Path::join( '.', 'phpstan.tooling.neon.dist' ),
				'--no-progress',
				'--memory-limit=1G',
			],
			$rootDir
		);
	}
}
