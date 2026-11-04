<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

/**
 * Browser lanes (ports, Compose projects, databases and the shared MySQL service) are machine-wide
 * Docker resources, so their leases live in one machine-scoped lock directory that every checkout,
 * terminal and agent shares.
 */
class BrowserTestLanePool {

	private const DEFAULT_POOL_SIZE = 8;
	private const MAX_POOL_SIZE = 20;
	private const DEFAULT_WAIT_SECONDS = 600;
	private const SHARED_SERVICES_WAIT_SECONDS = 120;
	private const LOCK_DIR_NAME = 'shield-test-locks/browser-lanes';
	private const SHARED_SERVICES_LOCK_FILE = 'shared-services.lock';

	private ?string $lockDir;

	public function __construct( ?string $lockDir = null ) {
		$this->lockDir = $lockDir;
	}

	/**
	 * Waits until a lane is free.
	 * @param callable|null $onOutput Receives (string $type, string $buffer)
	 * @param int[] $skipLaneIndexes
	 */
	public function acquire( ?callable $onOutput = null, array $skipLaneIndexes = [] ) :BrowserTestLaneLease {
		$waitSeconds = $this->waitSeconds();
		$startedAt = \time();
		$reportedWaiting = false;
		do {
			$lease = $this->tryAcquire( $skipLaneIndexes, $onOutput );
			if ( $lease !== null ) {
				return $lease;
			}
			if ( !$reportedWaiting ) {
				$this->writeProgress( 'Browser lane: all '.$this->poolSize().' lanes are leased; waiting', $onOutput );
				$reportedWaiting = true;
			}
			\usleep( 500000 );
		} while ( \time() - $startedAt < $waitSeconds );

		throw new \RuntimeException(
			'No browser test lane became available within '.$waitSeconds.' seconds. '
			.'Pool size: '.$this->poolSize().'. Lock directory: '.$this->lockDir().'. '
			.'Increase SHIELD_BROWSER_LANE_POOL_SIZE only if the machine can run more WordPress lanes.'
		);
	}

	/**
	 * @param int[] $skipLaneIndexes
	 * @param callable|null $onOutput Receives (string $type, string $buffer)
	 */
	public function tryAcquire( array $skipLaneIndexes = [], ?callable $onOutput = null ) :?BrowserTestLaneLease {
		$skipLaneIndexes = \array_flip( \array_map( 'intval', $skipLaneIndexes ) );
		$lockDir = $this->ensureLockDir();
		$poolSize = $this->poolSize();
		for ( $laneIndex = 1; $laneIndex <= $poolSize; $laneIndex++ ) {
			if ( isset( $skipLaneIndexes[ $laneIndex ] ) ) {
				continue;
			}
			$lockPath = $this->laneLockPath( $lockDir, $laneIndex );
			$handle = \fopen( $lockPath, 'c+' );
			if ( $handle === false ) {
				throw new \RuntimeException( 'Failed to open browser lane lock file: '.$lockPath );
			}
			if ( \flock( $handle, \LOCK_EX | \LOCK_NB ) ) {
				$this->writeLeaseMetadata( $handle, $laneIndex );
				$this->writeProgress( 'Browser lane: acquired lane '.$laneIndex.' of '.$poolSize, $onOutput );
				return new BrowserTestLaneLease(
					$laneIndex,
					$lockPath,
					$handle,
					LocalSiteDefinitions::browserLane( $laneIndex )
				);
			}
			\fclose( $handle );
		}

		return null;
	}

	/**
	 * True when any lane other than the given ones is leased by another process.
	 * @param int[] $heldLaneIndexes
	 */
	public function hasOtherActiveLeases( array $heldLaneIndexes ) :bool {
		$held = \array_flip( \array_map( 'intval', $heldLaneIndexes ) );
		foreach ( \glob( Path::join( $this->ensureLockDir(), 'lane-*.lock' ) ) ?: [] as $lockPath ) {
			if ( \preg_match( '/lane-(\d+)\.lock$/', $lockPath, $matches ) !== 1 || isset( $held[ (int)$matches[ 1 ] ] ) ) {
				continue;
			}
			$handle = @\fopen( $lockPath, 'c+' );
			if ( $handle === false ) {
				return true;
			}
			$isFree = \flock( $handle, \LOCK_EX | \LOCK_NB );
			if ( $isFree ) {
				\flock( $handle, \LOCK_UN );
			}
			\fclose( $handle );
			if ( !$isFree ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Serializes starting, reusing and removing the machine-wide shared services (MySQL and network).
	 * @template T
	 * @param callable():T $callback
	 * @return T
	 */
	public function withSharedServicesLock( callable $callback ) {
		$lockPath = Path::join( $this->ensureLockDir(), self::SHARED_SERVICES_LOCK_FILE );
		$handle = \fopen( $lockPath, 'c+' );
		if ( $handle === false ) {
			throw new \RuntimeException( 'Failed to open browser shared-services lock file: '.$lockPath );
		}

		$startedAt = \time();
		try {
			do {
				if ( \flock( $handle, \LOCK_EX | \LOCK_NB ) ) {
					return $callback();
				}
				\usleep( 500000 );
			} while ( \time() - $startedAt < self::SHARED_SERVICES_WAIT_SECONDS );

			throw new \RuntimeException( 'Timed out waiting for the browser shared-services lock: '.$lockPath );
		}
		finally {
			@\flock( $handle, \LOCK_UN );
			@\fclose( $handle );
		}
	}

	public function poolSize() :int {
		$poolSize = $this->positiveIntegerFromEnvironment( 'SHIELD_BROWSER_LANE_POOL_SIZE', self::DEFAULT_POOL_SIZE );
		if ( $poolSize > self::MAX_POOL_SIZE ) {
			throw new \InvalidArgumentException( 'SHIELD_BROWSER_LANE_POOL_SIZE must not exceed '.self::MAX_POOL_SIZE.'.' );
		}

		return $poolSize;
	}

	public function lockDir() :string {
		return $this->lockDir ?? Path::join( \sys_get_temp_dir(), self::LOCK_DIR_NAME );
	}

	private function ensureLockDir() :string {
		$lockDir = $this->lockDir();
		if ( !\is_dir( $lockDir ) && !@\mkdir( $lockDir, 0777, true ) && !\is_dir( $lockDir ) ) {
			throw new \RuntimeException( 'Failed to create browser lane lock directory: '.$lockDir );
		}

		return $lockDir;
	}

	private function laneLockPath( string $lockDir, int $laneIndex ) :string {
		return Path::join( $lockDir, 'lane-'.$laneIndex.'.lock' );
	}

	private function waitSeconds() :int {
		return $this->positiveIntegerFromEnvironment( 'SHIELD_BROWSER_LANE_WAIT_SECONDS', self::DEFAULT_WAIT_SECONDS );
	}

	private function positiveIntegerFromEnvironment( string $name, int $default ) :int {
		$value = \getenv( $name );
		if ( $value === false || $value === '' ) {
			return $default;
		}
		if ( !\ctype_digit( $value ) || (int)$value < 1 ) {
			throw new \InvalidArgumentException( $name.' must be a positive integer.' );
		}

		return (int)$value;
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
			'cwd' => (string)\getcwd(),
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
