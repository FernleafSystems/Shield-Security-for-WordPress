<?php
declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling;

use RuntimeException;

class PluginPackager {

	private string $projectRoot;

	/** @var callable */
	private $logger;

	public function __construct( ?string $projectRoot = null, ?callable $logger = null ) {
		$root = $projectRoot ?? $this->detectProjectRoot();
		if ( $root === '' ) {
			throw new RuntimeException( 'Unable to determine project root.' );
		}
		$this->projectRoot = $root;
		$this->logger = $logger ?? static function( string $message ) :void {
			echo $message.PHP_EOL;
		};
	}

	public function package( ?string $outputDir = null ) :string {
		$targetDir = $this->resolveOutputDirectory( $outputDir );
		$this->log( sprintf( 'Packaging Shield plugin to: %s', $targetDir ) );

		$composerCommand = $this->getComposerCommand();
		$this->runCommand(
			array_merge( $composerCommand, ['install', '--no-interaction', '--prefer-dist', '--optimize-autoloader'] ),
			$this->projectRoot
		);

		$this->runCommand(
			array_merge( $composerCommand, ['install', '--no-interaction', '--prefer-dist', '--optimize-autoloader'] ),
			$this->projectRoot.'/src/lib'
		);

		$this->runCommand(
			['npm', 'ci', '--no-audit', '--no-fund'],
			$this->projectRoot
		);

		$this->runCommand(
			['npm', 'run', 'build'],
			$this->projectRoot
		);

		$this->runCommand(
			['bash', $this->projectRoot.'/bin/build-package.sh', $targetDir, $this->projectRoot],
			$this->projectRoot
		);

		$this->log( sprintf( 'Package created at: %s', $targetDir ) );

		return $targetDir;
	}

	private function detectProjectRoot() :string {
		$root = realpath( __DIR__.'/../../..' );
		return $root === false ? '' : $root;
	}

	private function resolveOutputDirectory( ?string $path ) :string {
		if ( $path === null || $path === '' ) {
			$path = $this->projectRoot.'/tmp/shield-package-'.date( 'YmdHis' );
		}
		elseif ( !$this->isAbsolutePath( $path ) ) {
			$path = $this->projectRoot.'/'.$path;
		}

		return rtrim( $path, '/\\' );
	}

	private function isAbsolutePath( string $path ) :bool {
		return (bool)preg_match( '#^(?:[A-Za-z]:[\\/]|\\\\|/)#', $path );
	}

	private function runCommand( array $parts, ?string $workingDir = null ) :void {
		$command = $this->compileCommand( $parts );
		$this->log( '> '.$command );

		$previousCwd = getcwd();
		$revert = false;
		if ( $workingDir !== null && $workingDir !== '' && $previousCwd !== $workingDir ) {
			if ( !is_dir( $workingDir ) ) {
				throw new RuntimeException( sprintf( 'Working directory does not exist: %s', $workingDir ) );
			}
			if ( !@chdir( $workingDir ) ) {
				throw new RuntimeException( sprintf( 'Unable to change directory to: %s', $workingDir ) );
			}
			$revert = true;
		}

		try {
			passthru( $command, $exitCode );
		}
		finally {
			if ( $revert && is_string( $previousCwd ) && $previousCwd !== '' ) {
				@chdir( $previousCwd );
			}
		}

		if ( $exitCode !== 0 ) {
			throw new RuntimeException( sprintf( 'Command failed with exit code %d: %s', $exitCode, $command ) );
		}
	}

	private function compileCommand( array $parts ) :string {
		return implode( ' ', array_map( static function ( string $part ) :string {
			return escapeshellarg( $part );
		}, $parts ) );
	}

	private function log( string $message ) :void {
		( $this->logger )( $message );
	}

	/**
	 * @return string[]
	 */
	private function getComposerCommand() :array {
		$binary = getenv( 'COMPOSER_BINARY' );
		if ( !is_string( $binary ) || $binary === '' ) {
			$binary = 'composer';
		}

		$resolved = $this->resolveBinaryPath( $binary );
		if ( $this->endsWithPhar( $resolved ) ) {
			$php = PHP_BINARY ?: 'php';
			return [$php, $resolved];
		}

		return [$resolved];
	}

	private function resolveBinaryPath( string $binary ) :string {
		if ( $this->isAbsolutePath( $binary ) && file_exists( $binary ) ) {
			return $binary;
		}

		if ( strpos( $binary, '/' ) !== false || strpos( $binary, '\\' ) !== false ) {
			$fromRoot = $this->projectRoot.DIRECTORY_SEPARATOR.$binary;
			if ( file_exists( $fromRoot ) ) {
				return $fromRoot;
			}
		}

		$runtimeDir = getenv( 'COMPOSER_RUNTIME_BIN_DIR' );
		if ( is_string( $runtimeDir ) && $runtimeDir !== '' ) {
			$candidate = rtrim( $runtimeDir, '/\\' ).DIRECTORY_SEPARATOR.$binary;
			if ( file_exists( $candidate ) ) {
				return $candidate;
			}
		}

		return $binary;
	}

	private function endsWithPhar( string $path ) :bool {
		return substr( $path, -5 ) === '.phar';
	}
}
