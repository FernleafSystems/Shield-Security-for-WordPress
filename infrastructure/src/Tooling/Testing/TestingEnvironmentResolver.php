<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\BashCommandResolver;
use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

class TestingEnvironmentResolver {

	private ProcessRunner $processRunner;

	private BashCommandResolver $bashCommandResolver;

	public function __construct(
		?ProcessRunner $processRunner = null,
		?BashCommandResolver $bashCommandResolver = null
	) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->bashCommandResolver = $bashCommandResolver ?? new BashCommandResolver();
	}

	public function assertDockerReady( string $rootDir ) :void {
		if ( !$this->isDockerAvailable( $rootDir ) ) {
			throw new \RuntimeException( 'Docker is required but not found in PATH.' );
		}
		if ( !$this->isDockerDaemonRunning( $rootDir ) ) {
			throw new \RuntimeException( 'Docker daemon is not running.' );
		}
	}

	public function resolvePhpVersion( string $rootDir ) :string {
		$phpVersion = \trim( (string)( \getenv( 'PHP_VERSION' ) ?: '' ) );
		if ( $phpVersion !== '' ) {
			return $phpVersion;
		}
		return $this->readDefaultPhpFromMatrixConfig( $rootDir );
	}

	/**
	 * @return array{string,string}
	 */
	public function detectWordpressVersions( string $rootDir ) :array {
		$output = '';
		$process = $this->processRunner->run(
			[ $this->bashCommandResolver->resolve(), './.github/scripts/detect-wp-versions.sh' ],
			$rootDir,
			static function ( string $type, string $buffer ) use ( &$output ) :void {
				$output .= $buffer;
				if ( $type === Process::ERR ) {
					\fwrite( \STDERR, $buffer );
				}
				else {
					echo $buffer;
				}
			}
		);

		if ( ( $process->getExitCode() ?? 1 ) !== 0 ) {
			throw new \RuntimeException( 'WordPress version detection failed.' );
		}

		$latest = '';
		$previous = '';
		if ( \preg_match( '/^LATEST_VERSION=([^\r\n]+)/m', $output, $latestMatch ) === 1 ) {
			$latest = \trim( (string)( $latestMatch[ 1 ] ?? '' ) );
		}
		if ( \preg_match( '/^PREVIOUS_VERSION=([^\r\n]+)/m', $output, $previousMatch ) === 1 ) {
			$previous = \trim( (string)( $previousMatch[ 1 ] ?? '' ) );
		}

		if ( $latest === '' || $previous === '' ) {
			throw new \RuntimeException(
				'Could not parse LATEST_VERSION/PREVIOUS_VERSION from detect-wp-versions.sh output.'
			);
		}

		return [ $latest, $previous ];
	}

	/**
	 * @param string[] $lines
	 */
	public function writeDockerEnvFile( string $dockerEnvPath, array $lines ) :void {
		$dockerEnvDir = \dirname( $dockerEnvPath );
		if ( !\is_dir( $dockerEnvDir ) && !\mkdir( $dockerEnvDir, 0777, true ) && !\is_dir( $dockerEnvDir ) ) {
			throw new \RuntimeException( 'Failed to create Docker env directory: '.$dockerEnvDir );
		}

		$contents = \implode( \PHP_EOL, $lines ).\PHP_EOL;
		if ( \file_put_contents( $dockerEnvPath, $contents ) === false ) {
			throw new \RuntimeException( 'Failed to write Docker env file: '.$dockerEnvPath );
		}
	}

	/**
	 * @return array{strauss_version:?string,strauss_fork_repo:?string}
	 */
	public function resolvePackagerConfig( string $rootDir ) :array {
		$configPath = Path::join( $rootDir, '.github', 'config', 'packager.conf' );
		if ( !\is_file( $configPath ) ) {
			return [
				'strauss_version' => null,
				'strauss_fork_repo' => null,
			];
		}

		$lines = \file( $configPath, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES );
		if ( !\is_array( $lines ) ) {
			return [
				'strauss_version' => null,
				'strauss_fork_repo' => null,
			];
		}

		$values = [
			'strauss_version' => null,
			'strauss_fork_repo' => null,
		];

		foreach ( $lines as $line ) {
			$trimmed = \trim( $line );
			if ( $trimmed === '' || \strpos( $trimmed, '#' ) === 0 ) {
				continue;
			}

			if ( \preg_match( '/^STRAUSS_VERSION=(.+)$/', $trimmed, $matches ) === 1 ) {
				$values[ 'strauss_version' ] = \ltrim( \trim( (string)( $matches[ 1 ] ?? '' ), " \t\n\r\0\x0B\"'" ), 'v' );
				continue;
			}
			if ( \preg_match( '/^STRAUSS_FORK_REPO=(.+)$/', $trimmed, $matches ) === 1 ) {
				$values[ 'strauss_fork_repo' ] = \trim( (string)( $matches[ 1 ] ?? '' ), " \t\n\r\0\x0B\"'" );
			}
		}

		return $values;
	}

	private function isDockerAvailable( string $rootDir ) :bool {
		$process = $this->processRunner->run(
			[ 'docker', '--version' ],
			$rootDir,
			static function () :void {
			}
		);
		return ( $process->getExitCode() ?? 1 ) === 0;
	}

	private function isDockerDaemonRunning( string $rootDir ) :bool {
		$process = $this->processRunner->run(
			[ 'docker', 'info' ],
			$rootDir,
			static function () :void {
			}
		);
		return ( $process->getExitCode() ?? 1 ) === 0;
	}

	private function readDefaultPhpFromMatrixConfig( string $rootDir ) :string {
		$matrixFile = Path::join( $rootDir, '.github', 'config', 'matrix.conf' );
		if ( !\is_file( $matrixFile ) ) {
			return '8.2';
		}

		$content = (string)\file_get_contents( $matrixFile );
		if ( \preg_match( '/^DEFAULT_PHP="?([^"\r\n]+)"?/m', $content, $matches ) !== 1 ) {
			return '8.2';
		}

		$defaultPhp = \trim( (string)( $matches[ 1 ] ?? '' ) );
		return $defaultPhp !== '' ? $defaultPhp : '8.2';
	}
}
