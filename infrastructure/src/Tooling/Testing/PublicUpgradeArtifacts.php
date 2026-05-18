<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

class PublicUpgradeArtifacts {

	public const SUMMARY_FILE = 'upgrade-public-summary.json';
	public const PUBLIC_VERSION_FILE = 'public-version.json';
	public const PRIMING_REPORT_FILE = 'priming-report.json';
	public const UPDATE_RESULT_FILE = 'update-result.json';
	public const WP_CLI_LOG_FILE = 'wp-cli.log';
	public const WORDPRESS_DEBUG_LOG_FILE = 'wordpress-debug.log';
	public const ERROR_EVENTS_FILE = 'error-events.jsonl';
	public const DOCKER_LOG_FILE = 'docker.log';

	private const DEFAULT_DIR = 'tmp/upgrade-public';

	private string $dir;

	private bool $mirrorOutput;

	private function __construct( string $dir, bool $mirrorOutput ) {
		$this->dir = $dir;
		$this->mirrorOutput = $mirrorOutput;
	}

	public static function resolve( string $rootDir, ?string $explicitDir, bool $mirrorOutput = false ) :self {
		$dir = self::normalizeOptionalPath( $rootDir, $explicitDir );
		if ( $dir === null ) {
			$envDir = \getenv( 'SHIELD_UPGRADE_TEST_ARTIFACT_DIR' );
			$dir = self::normalizeOptionalPath( $rootDir, \is_string( $envDir ) ? $envDir : null );
		}
		if ( $dir === null ) {
			$dir = Path::join( $rootDir, self::DEFAULT_DIR );
		}

		return new self( $dir, $mirrorOutput );
	}

	public function dir() :string {
		return $this->dir;
	}

	public function path( string $file ) :string {
		return Path::join( $this->dir, $file );
	}

	public function resetForRun() :void {
		if ( !\is_dir( $this->dir ) && !\mkdir( $this->dir, 0777, true ) && !\is_dir( $this->dir ) ) {
			throw new \RuntimeException( 'Failed to create upgrade artifact directory: '.$this->dir );
		}

		foreach ( $this->knownFiles() as $file ) {
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
	 * @return string[]
	 */
	private function knownFiles() :array {
		return [
			self::SUMMARY_FILE,
			self::PUBLIC_VERSION_FILE,
			self::PRIMING_REPORT_FILE,
			self::UPDATE_RESULT_FILE,
			self::WP_CLI_LOG_FILE,
			self::WORDPRESS_DEBUG_LOG_FILE,
			self::ERROR_EVENTS_FILE,
			self::DOCKER_LOG_FILE,
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
