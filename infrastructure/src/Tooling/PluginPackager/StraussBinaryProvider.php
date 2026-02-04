<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\PluginPackager;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Provides Strauss binary for namespace prefixing, either from a fork clone or downloaded PHAR.
 */
class StraussBinaryProvider {

	private const FALLBACK_VERSION = '0.19.4';

	private string $version;

	private ?string $forkRepo;

	private CommandRunner $commandRunner;

	private SafeDirectoryRemover $directoryRemover;

	/** @var callable */
	private $logger;

	public function __construct(
		string $version,
		?string $forkRepo,
		CommandRunner $commandRunner,
		SafeDirectoryRemover $directoryRemover,
		?callable $logger = null
	) {
		$this->version = $version !== '' ? $version : self::FALLBACK_VERSION;
		$this->forkRepo = $forkRepo;
		$this->commandRunner = $commandRunner;
		$this->directoryRemover = $directoryRemover;
		$this->logger = $logger ?? static function ( string $message ) :void {
			echo $message.PHP_EOL;
		};
	}

	/**
	 * Provide Strauss binary - either from fork clone or downloaded PHAR.
	 * Returns the path to the strauss executable.
	 *
	 * @throws \RuntimeException if neither method succeeds
	 */
	public function provide( string $targetDir ) :string {
		if ( $this->forkRepo !== null ) {
			return $this->cloneAndPrepareStraussFork( $targetDir );
		}

		// Default: download official PHAR
		$this->downloadStraussPhar( $targetDir );
		return Path::join( $targetDir, 'strauss.phar' );
	}

	/**
	 * Run Strauss to prefix vendor namespaces.
	 *
	 * @throws \RuntimeException if Strauss execution fails
	 */
	public function runPrefixing( string $targetDir ) :void {
		$vendorPrefixedDir = Path::join( $targetDir, 'vendor_prefixed' );

		// Get path to strauss binary (either fork's bin/strauss or downloaded PHAR)
		$straussBinary = $this->provide( $targetDir );

		$this->log( sprintf( 'Current directory: %s', $targetDir ) );
		$this->log( sprintf( 'Using Strauss: %s', $straussBinary ) );
		$this->log( sprintf( 'Checking for composer.json: %s', file_exists( Path::join( $targetDir, 'composer.json' ) ) ? 'YES' : 'NO' ) );
		$this->log( '' );

		$this->log( 'Running Strauss prefixing...' );

		// Run strauss using PHP
		$php = PHP_BINARY ?: 'php';
		$this->commandRunner->run( [ $php, $straussBinary ], $targetDir );

		$this->log( '' );

		// Verify vendor_prefixed directory was created
		$this->log( '=== After Strauss ===' );
		if ( is_dir( $vendorPrefixedDir ) ) {
			$this->log( '✓ vendor_prefixed directory created' );
		}
		else {
			$this->log( '✗ vendor_prefixed NOT created' );
			throw new \RuntimeException(
				sprintf(
					'Strauss execution failed: vendor_prefixed directory was not created. '.
					'WHAT FAILED: Strauss completed without error but did not create the expected output directory. '.
					'Expected directory: %s. '.
					'WHY: This may indicate a Strauss configuration issue in composer.json or no packages matched for prefixing. '.
					'HOW TO FIX: Check the "extra.strauss" configuration in %s/composer.json.',
					$vendorPrefixedDir,
					$targetDir
				)
			);
		}
	}

	/**
	 * Detect if running inside a Docker container.
	 * Used to optimize file operations (e.g., use /tmp for ephemeral data).
	 */
	public static function isRunningInDocker() :bool {
		return \file_exists( '/.dockerenv' ) || getenv( 'SHIELD_TEST_MODE' ) === 'docker';
	}

	/**
	 * Get the fallback Strauss version.
	 */
	public static function getFallbackVersion() :string {
		return self::FALLBACK_VERSION;
	}

	/**
	 * Clone Strauss fork and prepare it for use.
	 * Returns path to bin/strauss executable.
	 *
	 * @throws \RuntimeException if clone or setup fails
	 */
	private function cloneAndPrepareStraussFork( string $targetDir ) :string {
		$forkHash = \substr( \md5( $this->forkRepo ), 0, 12 );

		// In Docker/Linux: use /tmp (no cross-drive issues, ephemeral so no cleanup needed)
		// On Windows: use target directory to avoid cross-drive path resolution issues
		if ( self::isRunningInDocker() ) {
			$forkDir = '/tmp/_strauss-fork-'.$forkHash;
		}
		else {
			// Clone to a temp directory WITHIN the target directory to ensure same drive on Windows.
			// This avoids cross-drive path resolution issues where Strauss (on C:) can't properly
			// calculate relative paths for a project on D:.
			$forkDir = Path::join( $targetDir, '_strauss-fork-'.$forkHash );
		}
		$binPath = Path::join( $forkDir, 'bin', 'strauss' );

		// Skip clone if already exists and has bin/strauss
		if ( is_dir( $forkDir ) && file_exists( $binPath ) ) {
			$this->log( sprintf( 'Using cached Strauss fork: %s', $forkDir ) );
			return $binPath;
		}

		// Clone fresh
		$this->log( sprintf( 'Cloning Strauss fork: %s', $this->forkRepo ) );

		if ( \is_dir( $forkDir ) ) {
			// Pass appropriate base path for safety check
			$allowedBase = self::isRunningInDocker() ? '/tmp' : $targetDir;
			$this->directoryRemover->removeTempDirectory( $forkDir, $allowedBase );
		}

		// Clone to parent directory since git clone creates the target
		$parentDir = \dirname( $forkDir );
		if ( !\is_dir( $parentDir ) ) {
			( new Filesystem() )->mkdir( $parentDir );
		}

		$this->commandRunner->run( [ 'git', 'clone', $this->forkRepo, $forkDir ], $parentDir );
		$this->commandRunner->run( [ 'git', 'checkout', 'develop' ], $forkDir );

		// Install dependencies (--no-scripts skips phive/dev tool hooks we don't need)
		$this->log( 'Installing Strauss fork dependencies...' );
		$composerCommand = $this->commandRunner->getComposerCommand();
		$this->commandRunner->run(
			array_merge( $composerCommand, [
				'install',
				'--no-interaction',
				'--no-dev',
				'--no-scripts',
				'--prefer-dist'
			] ),
			$forkDir
		);

		// Verify bin/strauss exists
		if ( !\file_exists( $binPath ) ) {
			throw new \RuntimeException(
				sprintf(
					'Strauss fork clone failed: bin/strauss not found at %s. '.
					'HOW TO FIX: Verify the fork repository URL is correct: %s',
					$binPath,
					$this->forkRepo
				)
			);
		}

		$this->log( '  ✓ Strauss fork ready' );
		$this->log( '' );
		return $binPath;
	}

	/**
	 * Download Strauss phar file for namespace prefixing.
	 *
	 * @throws \RuntimeException if download fails with detailed error message
	 */
	private function downloadStraussPhar( string $targetDir ) :void {
		$this->log( sprintf( 'Downloading Strauss v%s...', $this->version ) );

		$targetPath = Path::join( $targetDir, 'strauss.phar' );
		$downloadUrl = sprintf(
			'https://github.com/BrianHenryIE/strauss/releases/download/%s/strauss.phar',
			$this->version
		);

		// Check if allow_url_fopen is enabled
		if ( !ini_get( 'allow_url_fopen' ) ) {
			throw new \RuntimeException(
				'Strauss download failed: allow_url_fopen is disabled in PHP configuration. '.
				'WHAT FAILED: Unable to download strauss.phar from GitHub. '.
				'WHY: The PHP setting "allow_url_fopen" is disabled, which prevents PHP from downloading files from URLs. '.
				'HOW TO FIX: Enable allow_url_fopen in your php.ini file by setting "allow_url_fopen = On", '.
				'or contact your system administrator to enable this setting. '.
				'Alternatively, you can manually download strauss.phar from '.$downloadUrl.' '.
				'and place it in the package directory.'
			);
		}

		// Create HTTP context for the download
		$context = stream_context_create( [
			'http' => [
				'method'          => 'GET',
				'timeout'         => 30,
				'user_agent'      => 'Shield-Plugin-Packager/1.0',
				'follow_location' => true,
			],
			'ssl'  => [
				'verify_peer'      => true,
				'verify_peer_name' => true,
			],
		] );

		// Clear any previous errors
		error_clear_last();

		// Attempt to download the file
		$content = @file_get_contents( $downloadUrl, false, $context );

		if ( $content === false ) {
			$lastError = error_get_last();
			$errorMessage = $lastError !== null ? $lastError[ 'message' ] : 'Unknown error';

			throw new \RuntimeException(
				sprintf(
					'Strauss download failed: Unable to download strauss.phar from GitHub. '.
					'WHAT FAILED: Could not retrieve file from %s. '.
					'WHY: %s. '.
					'This may be due to: (1) Network connectivity issues, (2) GitHub server unavailability, '.
					'(3) Firewall or proxy blocking the connection, (4) SSL certificate verification failure. '.
					'HOW TO FIX: Check your internet connection and try again. If the problem persists, '.
					'you can manually download strauss.phar from the URL above and place it at: %s',
					$downloadUrl,
					$errorMessage,
					$targetPath
				)
			);
		}

		if ( $content === '' ) {
			throw new \RuntimeException(
				sprintf(
					'Strauss download failed: Downloaded file is empty. '.
					'WHAT FAILED: The file downloaded from %s has zero bytes. '.
					'WHY: This usually indicates a server-side issue or the release file was not found. '.
					'HOW TO FIX: Verify that Strauss version %s exists at the download URL. '.
					'You can manually download strauss.phar and place it at: %s',
					$downloadUrl,
					$this->version,
					$targetPath
				)
			);
		}

		// Clear any previous errors before writing
		error_clear_last();

		// Write the downloaded content to file
		$bytesWritten = @file_put_contents( $targetPath, $content );

		if ( $bytesWritten === false ) {
			$lastError = error_get_last();
			$errorMessage = $lastError !== null ? $lastError[ 'message' ] : 'Unknown error';

			throw new \RuntimeException(
				sprintf(
					'Strauss download failed: Could not write strauss.phar to disk. '.
					'WHAT FAILED: Unable to save downloaded file to %s. '.
					'WHY: %s. '.
					'This may be due to: (1) Insufficient disk space, (2) Directory does not exist, '.
					'(3) Insufficient file permissions, (4) Disk write protection. '.
					'HOW TO FIX: Ensure the target directory exists and is writable: %s',
					$targetPath,
					$errorMessage,
					$targetDir
				)
			);
		}

		// Verify the file was written correctly
		if ( !file_exists( $targetPath ) ) {
			throw new \RuntimeException(
				sprintf(
					'Strauss download failed: File was not created after write operation. '.
					'WHAT FAILED: strauss.phar does not exist at expected location after download. '.
					'Expected location: %s. '.
					'WHY: The file write operation reported success but the file is not present. '.
					'This is unusual and may indicate a filesystem issue. '.
					'HOW TO FIX: Check disk space and filesystem health. Try running the packaging again.',
					$targetPath
				)
			);
		}

		$fileSize = filesize( $targetPath );
		if ( $fileSize === 0 ) {
			throw new \RuntimeException(
				sprintf(
					'Strauss download failed: Downloaded file has zero size. '.
					'WHAT FAILED: strauss.phar exists but contains no data. '.
					'File location: %s. '.
					'WHY: The file was created but the content was not written correctly. '.
					'HOW TO FIX: Delete the empty file and try running the packaging again. '.
					'If the problem persists, manually download from: %s',
					$targetPath,
					$downloadUrl
				)
			);
		}

		$this->log( '  ✓ strauss.phar downloaded' );
		$this->log( '' );
	}

	private function log( string $message ) :void {
		( $this->logger )( $message );
	}
}
