<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Base {

	use ModConsumer;

	protected function isTempDirAvailable() :bool {
		return !empty( $this->getTempDir() );
	}

	protected function getTempDir() :string {
		$FS = Services::WpFs();
		try {
			$dir = $this->findTempDir();
			if ( basename( $dir ) === 'ptguard' ) {
				$oldDir = $dir;
				$dir = $this->generateNewDirName();
				foreach ( [ 'plugins', 'themes' ] as $type ) {
					$FS->moveDirContents( path_join( $oldDir, $type ), path_join( $dir, $type ) );
				}
				$FS->deleteDir( $oldDir );
			}
		}
		catch ( \Exception $e ) {
			$dir = $this->generateNewDirName();
		}
		return $dir;
	}

	private function generateNewDirName() :string {
		return $this->getCon()->cache_dir_handler->buildSubDir( 'ptguard-'.wp_generate_password( 16, false ) );
	}

	/**
	 * @throws \Exception
	 */
	private function findTempDir() :string {
		$FS = Services::WpFs();
		$dir = null;
		foreach ( $FS->getAllFilesInDir( $this->getCon()->cache_dir_handler->dir() ) as $fileItem ) {
			if ( strpos( basename( $fileItem ), 'ptguard' ) === 0 && $FS->isDir( $fileItem ) ) {
				$dir = $fileItem;
				break;
			}
		}
		if ( empty( $dir ) ) {
			throw new \Exception( "Dir doesn't exist" );
		}
		return $dir;
	}
}