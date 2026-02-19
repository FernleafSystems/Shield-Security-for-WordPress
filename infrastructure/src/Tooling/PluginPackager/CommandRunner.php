<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\PluginPackager;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

/**
 * Handles shell command execution using Symfony Process for cross-platform compatibility.
 */
class CommandRunner {

	private string $projectRoot;

	/** @var callable */
	private $logger;

	public function __construct( string $projectRoot, callable $logger ) {
		$this->projectRoot = $projectRoot;
		$this->logger = $logger;
	}

	/**
	 * Execute a shell command with proper cross-platform handling.
	 *
	 * Uses Symfony Process which automatically handles:
	 * - Argument escaping on all platforms (Windows, Linux, macOS)
	 * - Working directory management
	 * - Real-time output streaming
	 * - Proper exit code handling
	 *
	 * @param string[]    $parts      Command parts (first element is command, rest are arguments)
	 * @param string|null $workingDir Directory to run the command in
	 * @throws \RuntimeException|\Symfony\Component\Process\Exception\LogicException if command fails or working directory is invalid
	 */
	public function run( array $parts, ?string $workingDir = null ) :void {
		$cwd = $workingDir ?? $this->projectRoot;

		// Pre-validate working directory with consistent error message
		if ( !\is_dir( $cwd ) ) {
			throw new \RuntimeException( \sprintf(
				'Working directory does not exist: %s',
				$cwd
			) );
		}

		$this->log( '> '.\implode( ' ', $parts ) );

		$process = new Process(
			$parts,
			$cwd,
			null,  // env - inherit from parent
			null,  // input
			null   // timeout - no limit
		);

		// Run with real-time output streaming to console
		$exitCode = $process->run( function ( string $type, string $buffer ) :void {
			$this->writeOutputBuffer( $type, $buffer );
		} );

		if ( $exitCode !== 0 ) {
			$errorOutput = \trim( $process->getErrorOutput() );
			$message = \sprintf(
				'Command failed with exit code %d: %s',
				$exitCode,
				\implode( ' ', $parts )
			);
			if ( $errorOutput !== '' ) {
				$message .= "\nError output: ".$errorOutput;
			}
			throw new \RuntimeException( $message );
		}
	}

	/**
	 * Get the composer command array (handles PHAR files by prepending PHP binary).
	 *
	 * @return string[] Command parts for composer
	 */
	public function getComposerCommand() :array {
		$binary = \getenv( 'COMPOSER_BINARY' );
		if ( !\is_string( $binary ) || $binary === '' ) {
			$binary = 'composer';
		}

		$resolved = $this->resolveBinaryPath( $binary );
		if ( $this->endsWithPhar( $resolved ) ) {
			$php = \PHP_BINARY ?: 'php';
			return [ $php, $resolved ];
		}

		return [ $resolved ];
	}

	/**
	 * Resolve a binary path, checking various locations.
	 */
	private function resolveBinaryPath( string $binary ) :string {
		if ( $this->isAbsolutePath( $binary ) && \file_exists( $binary ) ) {
			return $binary;
		}

		if ( \strpos( $binary, '/' ) !== false || \strpos( $binary, '\\' ) !== false ) {
			$fromRoot = Path::join( $this->projectRoot, $binary );
			if ( \file_exists( $fromRoot ) ) {
				return $fromRoot;
			}
		}

		$runtimeDir = \getenv( 'COMPOSER_RUNTIME_BIN_DIR' );
		if ( \is_string( $runtimeDir ) && $runtimeDir !== '' ) {
			$candidate = Path::join( \rtrim( $runtimeDir, '/\\' ), $binary );
			if ( \file_exists( $candidate ) ) {
				return $candidate;
			}
		}

		return $binary;
	}

	/**
	 * Check if a path ends with .phar extension.
	 */
	private function endsWithPhar( string $path ) :bool {
		return \substr( $path, -5 ) === '.phar';
	}

	/**
	 * Check if a path is absolute (handles all platforms).
	 */
	private function isAbsolutePath( string $path ) :bool {
		$path = \trim( $path, " \t\n\r\0\x0B\"'" );
		return $path !== '' && Path::isAbsolute( $path );
	}

	private function log( string $message ) :void {
		( $this->logger )( $message );
	}

	private function writeOutputBuffer( string $type, string $buffer ) :void {
		$normalized = $this->normalizeOutputLineEndings( $buffer );

		if ( $type === Process::ERR ) {
			\fwrite( \STDERR, $normalized );
		}
		else {
			echo $normalized;
		}
	}

	private function normalizeOutputLineEndings( string $buffer ) :string {
		$normalized = \str_replace( [ "\r\n", "\r" ], "\n", $buffer );
		if ( \PHP_EOL !== "\n" ) {
			$normalized = \str_replace( "\n", \PHP_EOL, $normalized );
		}
		return $normalized;
	}
}
