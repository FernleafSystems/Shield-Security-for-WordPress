<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use Symfony\Component\Filesystem\Path;

/**
 * Assigns each linked Git worktree its own manual test-site slot, recorded in a machine-scoped registry
 * so a worktree keeps its slot (port, Compose project, database) across runs.
 */
class LocalTestSiteSlots {

	public const MAX_SLOTS = 17;

	private const REGISTRY_DIR_NAME = 'shield-test-locks';
	private const REGISTRY_FILE = 'test-site-slots.json';

	private ?string $registryDir;

	public function __construct( ?string $registryDir = null ) {
		$this->registryDir = $registryDir;
	}

	public function slotFor( string $checkoutDir ) :int {
		$registryDir = $this->registryDir ?? Path::join( \sys_get_temp_dir(), self::REGISTRY_DIR_NAME );
		if ( !\is_dir( $registryDir ) && !@\mkdir( $registryDir, 0777, true ) && !\is_dir( $registryDir ) ) {
			throw new \RuntimeException( 'Failed to create test-site slot registry directory: '.$registryDir );
		}
		$registryPath = Path::join( $registryDir, self::REGISTRY_FILE );
		$handle = \fopen( $registryPath, 'c+' );
		if ( $handle === false || !\flock( $handle, \LOCK_EX ) ) {
			throw new \RuntimeException( 'Failed to lock test-site slot registry: '.$registryPath );
		}

		try {
			$decoded = \json_decode( (string)\stream_get_contents( $handle ), true );
			$slots = [];
			foreach ( \is_array( $decoded ) ? $decoded : [] as $dir => $slot ) {
				if ( \is_string( $dir ) && \is_int( $slot ) && \is_dir( $dir ) ) {
					$slots[ $dir ] = $slot;
				}
			}

			$key = $this->checkoutKey( $checkoutDir );
			if ( !isset( $slots[ $key ] ) ) {
				$free = \array_values( \array_diff( \range( 1, self::MAX_SLOTS ), $slots ) );
				if ( $free === [] ) {
					throw new \RuntimeException( 'All '.self::MAX_SLOTS.' worktree test-site slots are in use. Registry: '.$registryPath );
				}
				$slots[ $key ] = $free[ 0 ];
			}

			\rewind( $handle );
			\ftruncate( $handle, 0 );
			\fwrite( $handle, \json_encode( $slots, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES ).\PHP_EOL );
			\fflush( $handle );

			return $slots[ $key ];
		}
		finally {
			\flock( $handle, \LOCK_UN );
			\fclose( $handle );
		}
	}

	private function checkoutKey( string $checkoutDir ) :string {
		$key = Path::normalize( \realpath( $checkoutDir ) ?: $checkoutDir );
		return \DIRECTORY_SEPARATOR === '\\' ? \strtolower( $key ) : $key;
	}
}
