<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;

class PhpSyntaxLinter {

	private ProcessRunner $processRunner;

	public function __construct( ?ProcessRunner $processRunner = null ) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
	}

	/**
	 * @param string[] $relativePaths
	 */
	public function lint( string $rootDir, array $relativePaths ) :PhpSyntaxLintReport {
		$files = $this->discoverPhpFiles( $rootDir, $relativePaths );
		$failures = [];

		foreach ( $files as $relativePath ) {
			$process = $this->processRunner->run(
				[ \PHP_BINARY, '-l', $relativePath ],
				$rootDir,
				static function () :void {
				}
			);
			$exitCode = $process->getExitCode() ?? 1;

			if ( $exitCode !== 0 ) {
				$output = \trim( $process->getOutput().$process->getErrorOutput() );
				$failures[] = [
					'path' => $relativePath,
					'output' => $output,
					'exit_code' => $exitCode,
				];
			}
		}

		return new PhpSyntaxLintReport( \count( $files ), $failures );
	}

	/**
	 * @param string[] $relativePaths
	 * @return string[]
	 */
	private function discoverPhpFiles( string $rootDir, array $relativePaths ) :array {
		$files = [];

		foreach ( $relativePaths as $relativePath ) {
			$normalizedRelativePath = Path::normalize( $relativePath );
			$fullPath = Path::join( $rootDir, $normalizedRelativePath );

			if ( !\file_exists( $fullPath ) ) {
				throw new \RuntimeException( 'Path does not exist for syntax lint: '.$normalizedRelativePath );
			}

			if ( \is_file( $fullPath ) ) {
				if ( $this->isPhpFile( $fullPath ) ) {
					$files[ $normalizedRelativePath ] = $normalizedRelativePath;
				}
				continue;
			}

			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $fullPath, \FilesystemIterator::SKIP_DOTS )
			);
			foreach ( $iterator as $item ) {
				if ( !$item instanceof \SplFileInfo || !$item->isFile() ) {
					continue;
				}

				$filePath = $item->getPathname();
				if ( !$this->isPhpFile( $filePath ) ) {
					continue;
				}

				$relativeFilePath = Path::normalize( Path::makeRelative( $filePath, $rootDir ) );
				$files[ $relativeFilePath ] = $relativeFilePath;
			}
		}

		\sort( $files );
		return \array_values( $files );
	}

	private function isPhpFile( string $filePath ) :bool {
		$extension = \strtolower( (string)\pathinfo( $filePath, \PATHINFO_EXTENSION ) );
		if ( $extension === 'php' ) {
			return true;
		}

		$prefix = \file_get_contents( $filePath, false, null, 0, 256 );
		if ( !\is_string( $prefix ) ) {
			return false;
		}

		return \strpos( $prefix, '<?php' ) !== false || \strpos( $prefix, '#!/usr/bin/env php' ) === 0;
	}
}
