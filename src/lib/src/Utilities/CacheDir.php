<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\TestCacheDirWrite;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CacheDir {

	use PluginControllerConsumer;

	public function build() :string {
		$con = $this->getCon();

		$dir = '';
		try {
			$maybeDir = $this->getDir();
			if ( !isset( $con->cache_dir_ready ) ) {
				if ( !Services::WpFs()->mkdir( $maybeDir ) ) {
					throw new \Exception( 'Failed to mkdir cache dir' );
				}
				$this->testWrite();
				$this->addProtections();
				$con->cache_dir_ready = true;
			}
			if ( $con->cache_dir_ready ) {
				$dir = $maybeDir;
			}
		}
		catch ( \Exception $e ) {
			$con->cache_dir_ready = false;
		}
		return $dir;
	}

	public function buildSubDir( string $subDir ) :string {
		$finalDir = '';
		$baseDir = $this->build();
		if ( !empty( $baseDir ) ) {
			$FS = Services::WpFs();
			$finalDir = path_join( $baseDir, $subDir );
			if ( !$FS->mkdir( $finalDir ) ) {
				$finalDir = '';
			}
		}
		return $finalDir;
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	private function testWrite() :bool {
		$tester = ( new TestCacheDirWrite() )->setMod( $this->getCon()->getModule_Plugin() );
		if ( !$tester->canWrite() ) {
			throw new \Exception( 'Failed Test-Write' );
		}
		return true;
	}

	private function addProtections() :bool {
		$FS = Services::WpFs();
		$cacheDir = $this->getDir();

		$htFile = path_join( $cacheDir, '.htaccess' );
		$htContent = implode( "\n", [
			"# BEGIN SHIELD",
			"Options -Indexes",
			"Order allow,deny",
			"Deny from all",
			'<FilesMatch "^.*\.(css|js)$">',
			" Allow from all",
			'</FilesMatch>',
			"# END SHIELD"
		] );
		if ( !$FS->exists( $htFile ) || ( md5_file( $htFile ) !== md5( $htContent ) ) ) {
			$FS->putFileContent( $htFile, $htContent );
		}
		$index = path_join( $cacheDir, 'index.php' );
		$indexContent = "<?php\nhttp_response_code(404);";
		if ( !$FS->exists( $index ) || ( md5_file( $index ) !== md5( $indexContent ) ) ) {
			$FS->putFileContent( $index, $indexContent );
		}

		return true;
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	private function getDir() :string {
		$con = $this->getCon();
		if ( empty( $con->cfg->paths[ 'cache' ] ) ) {
			throw new \Exception( 'No slug for cache dir' );
		}
		return $this->getCon()->paths->cacheDir();
	}
}