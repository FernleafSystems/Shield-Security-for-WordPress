<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CacheDir {

	use PluginControllerConsumer;

	public function build() :string {
		$con = $this->getCon();

		try {
			$dir = $this->getDir();
			if ( !isset( $con->cache_dir_ready ) ) {
				$this->addProtections();
				$con->cache_dir_ready = true;
			}
		}
		catch ( \Exception $e ) {
			$dir = '';
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
		$dir = path_join( WP_CONTENT_DIR, $con->cfg->paths[ 'cache' ] );
		if ( !Services::WpFs()->mkdir( $dir ) ) {
			throw new \Exception( 'Failed to create cache dir' );
		}
		return $dir;
	}
}