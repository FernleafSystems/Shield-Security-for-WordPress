<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\FileSystemUtils;
use Symfony\Component\Filesystem\Path;

trait TempDirLifecycleTrait {

	/**
	 * @var string[]
	 */
	private array $trackedTempDirs = [];

	protected function createTrackedTempDir( string $prefix = 'shield-test-' ) :string {
		$path = Path::join( \sys_get_temp_dir(), $prefix.\bin2hex( \random_bytes( 6 ) ) );
		if ( !\is_dir( $path ) && !\mkdir( $path, 0777, true ) && !\is_dir( $path ) ) {
			throw new \RuntimeException( 'Failed to create temporary directory: '.$path );
		}
		$this->trackedTempDirs[] = $path;
		return $path;
	}

	protected function cleanupTrackedTempDirs() :void {
		foreach ( $this->trackedTempDirs as $path ) {
			FileSystemUtils::removeDirectoryRecursive( $path );
		}
		$this->trackedTempDirs = [];
	}
}
