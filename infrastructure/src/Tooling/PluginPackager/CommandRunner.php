<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\PluginPackager;

use Symfony\Component\Filesystem\Path;

/**
 * Handles shell command execution with proper escaping and working directory management.
 */
class CommandRunner {

	private string $projectRoot;

	/** @var callable */
	private $logger;

	public function __construct( string $projectRoot, ?callable $logger = null ) {
		$this->projectRoot = $projectRoot;
		$this->logger = $logger ?? static function ( string $message ) :void {
			echo $message.PHP_EOL;
		};
	}

	/**
	 * Execute a shell command with proper escaping and working directory management.
	 *
	 * @param string[]    $parts      Command parts (first element is command, rest are arguments)
	 * @param string|null $workingDir Directory to run the command in
	 * @throws \RuntimeException if command fails or working directory is invalid
	 */
	public function run( array $parts, ?string $workingDir = null ) :void {
		// Build command string with proper escaping for cross-platform compatibility
		// Command names from PATH (like 'npm', 'composer') don't need quotes
		// But full paths (especially with spaces) do need quotes
		// Arguments always get quoted to handle spaces and special characters
		$command = \array_shift( $parts );

		// Check if command is a full path (contains directory separators)
		// If so, quote it to handle spaces; otherwise leave unquoted for PATH resolution
		$needsQuoting = \strpos( $command, DIRECTORY_SEPARATOR ) !== false
						|| \strpos( $command, '/' ) !== false  // Handle Unix paths on Windows
						|| \strpos( $command, '\\' ) !== false; // Handle Windows paths

		$commandPart = $needsQuoting ? escapeshellarg( $command ) : $command;
		$args = \array_map( static function ( string $part ) :string {
			return \escapeshellarg( $part );
		}, $parts );
		$commandString = $commandPart.( empty( $args ) ? '' : ' '.implode( ' ', $args ) );
		$this->log( '> '.$commandString );

		$previousCwd = \getcwd();
		$revert = false;
		if ( $workingDir !== null && $workingDir !== '' && $previousCwd !== $workingDir ) {
			if ( !\is_dir( $workingDir ) ) {
				throw new \RuntimeException( sprintf( 'Working directory does not exist: %s', $workingDir ) );
			}
			if ( !@chdir( $workingDir ) ) {
				throw new \RuntimeException( sprintf( 'Unable to change directory to: %s', $workingDir ) );
			}
			$revert = true;
		}

		try {
			\passthru( $commandString, $exitCode );
		}
		finally {
			if ( $revert && \is_string( $previousCwd ) && $previousCwd !== '' ) {
				@\chdir( $previousCwd );
			}
		}

		if ( $exitCode !== 0 ) {
			throw new \RuntimeException( sprintf( 'Command failed with exit code %d: %s', $exitCode, $commandString ) );
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
		if ( $this->isAbsolutePath( $binary ) && file_exists( $binary ) ) {
			return $binary;
		}

		if ( \strpos( $binary, '/' ) !== false || \strpos( $binary, '\\' ) !== false ) {
			$fromRoot = Path::join( $this->projectRoot, $binary );
			if ( file_exists( $fromRoot ) ) {
				return $fromRoot;
			}
		}

		$runtimeDir = getenv( 'COMPOSER_RUNTIME_BIN_DIR' );
		if ( is_string( $runtimeDir ) && $runtimeDir !== '' ) {
			$candidate = Path::join( rtrim( $runtimeDir, '/\\' ), $binary );
			if ( file_exists( $candidate ) ) {
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
		// Use Symfony Filesystem Path::isAbsolute() which handles all platforms correctly
		// Path should already be trimmed by caller, but trim again for safety
		$path = \trim( $path, " \t\n\r\0\x0B\"'" );
		return $path !== '' && Path::isAbsolute( $path );
	}

	private function log( string $message ) :void {
		( $this->logger )( $message );
	}
}
