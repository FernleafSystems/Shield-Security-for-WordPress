<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\TestCacheDirWrite;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CacheDir {

	use PluginControllerConsumer;

	private $cacheDir;

	public function dirExists() :bool {
		$dir = $this->build();
		return !empty( $dir ) && Services::WpFs()->isDir( $dir );
	}

	public function build() :string {
		if ( !isset( $this->cacheDir ) ) {
			$this->cacheDir = '';
			try {
				$maybeDir = $this->getDir();
				if ( !Services::WpFs()->mkdir( $maybeDir ) ) {
					throw new \Exception( 'Failed to mkdir cache dir' );
				}
				$this->testWrite();
				$this->addProtections();
				$this->cacheDir = $maybeDir;
			}
			catch ( \Exception $e ) {
				/* error_log( sprintf( 'Exception making the cache dir: %s; Exception: %s', $maybeDir ?? 'emptydir', $e->getMessage() ) ); */
			}
		}

		return $this->cacheDir;
	}

	public function buildSubDir( string $subDir ) :string {
		$finalDir = '';
		if ( $this->dirExists() ) {
			$FS = Services::WpFs();
			$baseDir = $this->build();
			$finalDir = path_join( $baseDir, $subDir );
			if ( !$FS->mkdir( $finalDir ) ) {
				$finalDir = '';
			}
		}
		return $finalDir;
	}

	/**
	 * @throws \Exception
	 */
	private function testWrite() :bool {
		$tester = ( new TestCacheDirWrite() )->setMod( $this->getCon()->getModule_Plugin() );
		if ( !$tester->canWrite() ) {
			throw new \Exception( 'Failed Test-Write' );
		}
		return true;
	}

	/**
	 * @throws \Exception
	 */
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