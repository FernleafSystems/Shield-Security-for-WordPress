<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

class WordPressPackageRuntimeArtifacts {

	public const WP_CLI_LOG_FILE = 'wp-cli.log';
	public const WORDPRESS_DEBUG_LOG_FILE = 'wordpress-debug.log';
	public const ERROR_EVENTS_FILE = 'error-events.jsonl';
	public const DOCKER_LOG_FILE = 'docker.log';

	private string $dir;

	private bool $mirrorOutput;

	private string $summaryFile;

	/** @var array<string,string> */
	private array $specificArtifactFiles;

	/** @var string[] */
	private array $knownFiles;

	/**
	 * @param array<string,string> $specificArtifactFiles
	 */
	protected function __construct( string $dir, bool $mirrorOutput, string $summaryFile, array $specificArtifactFiles ) {
		$this->dir = $dir;
		$this->mirrorOutput = $mirrorOutput;
		$this->summaryFile = $summaryFile;
		$this->specificArtifactFiles = $specificArtifactFiles;
		$this->knownFiles = \array_values( \array_unique( \array_merge(
			[ $summaryFile ],
			\array_values( $specificArtifactFiles ),
			\array_values( $this->commonFiles() )
		) ) );
	}

	protected static function resolveRuntimeDir(
		string $rootDir,
		?string $explicitDir,
		?string $envVar,
		string $defaultDir
	) :string {
		$dir = self::normalizeOptionalPath( $rootDir, $explicitDir );
		if ( $dir === null && \is_string( $envVar ) && $envVar !== '' ) {
			$envDir = \getenv( $envVar );
			$dir = self::normalizeOptionalPath( $rootDir, \is_string( $envDir ) ? $envDir : null );
		}
		if ( $dir === null ) {
			$dir = Path::join( $rootDir, $defaultDir );
		}

		return $dir;
	}

	public function dir() :string {
		return $this->dir;
	}

	public function path( string $file ) :string {
		return Path::join( $this->dir, $file );
	}

	public function resetForRun() :void {
		if ( !\is_dir( $this->dir ) && !\mkdir( $this->dir, 0777, true ) && !\is_dir( $this->dir ) ) {
			throw new \RuntimeException( 'Failed to create runtime artifact directory: '.$this->dir );
		}

		foreach ( $this->knownFiles as $file ) {
			$path = $this->path( $file );
			if ( \is_file( $path ) ) {
				\unlink( $path );
			}
		}
	}

	public function appendLogHeading( string $label ) :void {
		$this->appendWpCliLog( \PHP_EOL.'## '.$label.\PHP_EOL );
	}

	public function appendWpCliLog( string $buffer ) :void {
		\file_put_contents( $this->path( self::WP_CLI_LOG_FILE ), $buffer, \FILE_APPEND );
	}

	public function writeJson( string $file, array $payload ) :void {
		\file_put_contents(
			$this->path( $file ),
			\json_encode( $payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR ).\PHP_EOL
		);
	}

	public function writeSummary( array $payload ) :void {
		$this->writeJson( $this->summaryFile, $payload );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function baseSummary( int $setupFailureExitCode ) :array {
		return [
			'status'       => 'running',
			'exit_code'    => $setupFailureExitCode,
			'artifact_dir' => $this->dir(),
			'started_at'   => \gmdate( DATE_ATOM ),
			'artifacts'    => \array_map(
				fn( string $file ) :string => $this->path( $file ),
				\array_merge(
					[ 'summary' => $this->summaryFile ],
					$this->specificArtifactFiles,
					$this->commonFiles()
				)
			),
		];
	}

	/**
	 * @param array<string,mixed> $summary
	 * @return array<string,mixed>
	 */
	public function withPackageMetadata( array $summary, PublicUpgradePackageZipMetadata $metadata ) :array {
		$summary[ 'package_zip' ] = $metadata->zipPath();
		$summary[ 'package_version' ] = $metadata->version();
		$summary[ 'package_plugin_file' ] = $metadata->pluginFile();
		return $summary;
	}

	public function ensureFileExists( string $file ) :void {
		$path = $this->path( $file );
		if ( !\is_file( $path ) ) {
			\file_put_contents( $path, '' );
		}
	}

	public function processOutputCallback() :callable {
		return function ( string $type, string $buffer ) :void {
			$this->appendWpCliLog( $buffer );
			if ( $this->mirrorOutput ) {
				if ( $type === Process::ERR ) {
					\fwrite( \STDERR, $buffer );
				}
				else {
					echo $buffer;
				}
			}
		};
	}

	/**
	 * @return array<string,string>
	 */
	private function commonFiles() :array {
		return [
			'wp_cli_log'      => self::WP_CLI_LOG_FILE,
			'wordpress_debug' => self::WORDPRESS_DEBUG_LOG_FILE,
			'error_events'    => self::ERROR_EVENTS_FILE,
			'docker_log'      => self::DOCKER_LOG_FILE,
		];
	}

	private static function normalizeOptionalPath( string $rootDir, ?string $path ) :?string {
		if ( !\is_string( $path ) ) {
			return null;
		}

		$trimmed = \trim( $path, " \t\n\r\0\x0B\"'" );
		if ( $trimmed === '' ) {
			return null;
		}

		$normalized = Path::normalize( $trimmed );
		return Path::isAbsolute( $normalized ) ? $normalized : Path::makeAbsolute( $normalized, $rootDir );
	}
}
