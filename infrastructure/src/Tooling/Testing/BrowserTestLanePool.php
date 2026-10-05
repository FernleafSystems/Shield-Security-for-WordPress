<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

class BrowserTestLanePool {

	private const DEFAULT_LANE_COUNT = 2;
	private const DEFAULT_WAIT_SECONDS = 600;
	private const LOCK_DIR = 'tmp/browser-test-lanes';
	private const SHARED_SERVICE_LOCK_DIR = 'shield-browser-test-lanes';
	private const SHARED_SERVICE_LOCK_FILE = 'shared-services.lock';

	private ?string $sharedServiceLockDir = null;

	/** @var array<string,array{pid:int,handle:resource}> */
	private static array $sharedServiceAdmissions = [];

	public function __construct( ?string $sharedServiceLockDir = null ) {
		$this->sharedServiceLockDir = $sharedServiceLockDir;
	}

	/**
	 * Serialize browser service owners across checkouts, retaining admission through cleanup.
	 * Nested owners in the same process share the live handle, never diagnostic metadata.
	 *
	 * @param callable():int $callback
	 */
	public function withSharedServiceAdmission( string $rootDir, callable $callback, ?callable $onOutput = null ) :int {
		$lockDir = $this->sharedServiceLockDir ?? Path::join( \rtrim( \sys_get_temp_dir(), "\\/" ), self::SHARED_SERVICE_LOCK_DIR );
		if ( !\is_dir( $lockDir ) && !@\mkdir( $lockDir, 0777, true ) && !\is_dir( $lockDir ) ) {
			throw new \RuntimeException( 'Failed to create browser shared-service lock directory: '.$lockDir );
		}
		$resolvedLockDir = \realpath( $lockDir );
		if ( $resolvedLockDir === false ) {
			throw new \RuntimeException( 'Failed to resolve browser shared-service lock directory: '.$lockDir );
		}
		$lockPath = Path::join( $resolvedLockDir, self::SHARED_SERVICE_LOCK_FILE );
		$admissionKey = \DIRECTORY_SEPARATOR === '\\' ? \strtolower( $lockPath ) : $lockPath;
		$pid = (int)\getmypid();
		$admission = self::$sharedServiceAdmissions[ $admissionKey ] ?? null;
		if ( $admission !== null && $admission[ 'pid' ] === $pid && \is_resource( $admission[ 'handle' ] ) ) {
			return $callback();
		}

		$waitSeconds = $this->waitSeconds();
		$handle = \fopen( $lockPath, 'c+' );
		if ( $handle === false ) {
			throw new \RuntimeException( 'Failed to open browser shared-service lock file: '.$lockPath );
		}
		$startedAt = \microtime( true );
		$reportedWaiting = false;
		try {
			do {
				if ( \flock( $handle, \LOCK_EX | \LOCK_NB ) ) {
					self::$sharedServiceAdmissions[ $admissionKey ] = [ 'pid' => $pid, 'handle' => $handle ];
					\rewind( $handle );
					\ftruncate( $handle, 0 );
					\fwrite( $handle, \json_encode( [
						'pid' => $pid,
						'root_dir' => $rootDir,
						'acquired_at_unix' => \time(),
					], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES ).\PHP_EOL );
					\fflush( $handle );
					return $callback();
				}
				if ( !$reportedWaiting ) {
					$this->writeProgress( 'Browser shared services: waiting for admission', $onOutput );
					$reportedWaiting = true;
				}
				\usleep( 500000 );
			} while ( \microtime( true ) - $startedAt < $waitSeconds );
			throw new \RuntimeException( 'No browser shared-service admission became available within '.$waitSeconds.' seconds. Lock: '.$lockPath );
		}
		finally {
			if ( ( self::$sharedServiceAdmissions[ $admissionKey ][ 'handle' ] ?? null ) === $handle ) {
				unset( self::$sharedServiceAdmissions[ $admissionKey ] );
			}
			@\flock( $handle, \LOCK_UN );
			\fclose( $handle );
		}
	}

	/**
	 * @param callable|null $onOutput Receives (string $type, string $buffer)
	 * @param int[] $skipLaneIndexes
	 */
	public function acquire(
		string $rootDir,
		?callable $onOutput = null,
		array $skipLaneIndexes = [],
		?int $laneCountOverride = null
	) :BrowserTestLaneLease {
		$laneCount = $this->laneCount( $laneCountOverride );
		$waitSeconds = $this->waitSeconds();
		$skipLaneIndexes = \array_flip( \array_map( 'intval', $skipLaneIndexes ) );
		$lockDir = Path::join( $rootDir, self::LOCK_DIR );
		if ( !\is_dir( $lockDir ) && !\mkdir( $lockDir, 0777, true ) && !\is_dir( $lockDir ) ) {
			throw new \RuntimeException( 'Failed to create browser lane lock directory: '.$lockDir );
		}

		$startedAt = \time();
		do {
			for ( $laneIndex = 1; $laneIndex <= $laneCount; $laneIndex++ ) {
				if ( isset( $skipLaneIndexes[ $laneIndex ] ) ) {
					continue;
				}
				$lockPath = Path::join( $lockDir, 'lane-'.$laneIndex.'.lock' );
				$handle = \fopen( $lockPath, 'c+' );
				if ( $handle === false ) {
					throw new \RuntimeException( 'Failed to open browser lane lock file: '.$lockPath );
				}
				if ( \flock( $handle, \LOCK_EX | \LOCK_NB ) ) {
					$this->writeLeaseMetadata( $handle, $laneIndex );
					$this->writeProgress( 'Browser lane: acquired lane '.$laneIndex.' of '.$laneCount, $onOutput );
					return new BrowserTestLaneLease(
						$laneIndex,
						$lockPath,
						$handle,
						LocalSiteDefinitions::browserLane( $laneIndex )
					);
				}
				\fclose( $handle );
			}

			\usleep( 500000 );
		} while ( \time() - $startedAt < $waitSeconds );

		throw new \RuntimeException(
			'No browser test lane became available within '.$waitSeconds.' seconds. '
			.'Lane count: '.$laneCount.'. Lock directory: '.$lockDir.'. '
			.'Increase SHIELD_BROWSER_LANE_COUNT only if the machine can run more WordPress lanes.'
		);
	}

	public function laneCount( ?int $laneCountOverride = null ) :int {
		if ( $laneCountOverride !== null ) {
			if ( $laneCountOverride < 1 ) {
				throw new \InvalidArgumentException( 'Browser lane count must be a positive integer.' );
			}
			return $laneCountOverride;
		}
		return $this->positiveIntegerFromEnvironment( 'SHIELD_BROWSER_LANE_COUNT', self::DEFAULT_LANE_COUNT );
	}

	private function waitSeconds() :int {
		return $this->positiveIntegerFromEnvironment( 'SHIELD_BROWSER_LANE_WAIT_SECONDS', self::DEFAULT_WAIT_SECONDS );
	}

	private function positiveIntegerFromEnvironment( string $name, int $default ) :int {
		$value = \getenv( $name );
		if ( $value === false || $value === '' ) {
			return $default;
		}
		return PositiveIntegerInput::parse( $value, $name );
	}

	/**
	 * @param resource $handle
	 */
	private function writeLeaseMetadata( $handle, int $laneIndex ) :void {
		\rewind( $handle );
		\ftruncate( $handle, 0 );
		\fwrite( $handle, \json_encode( [
			'lane' => $laneIndex,
			'pid' => \getmypid(),
			'acquired_at_unix' => \time(),
		], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES ).\PHP_EOL );
		\fflush( $handle );
	}

	/**
	 * @param callable|null $onOutput Receives (string $type, string $buffer)
	 */
	private function writeProgress( string $message, ?callable $onOutput = null ) :void {
		if ( $onOutput !== null ) {
			$onOutput( Process::OUT, $message.\PHP_EOL );
			return;
		}

		echo $message.\PHP_EOL;
	}
}
