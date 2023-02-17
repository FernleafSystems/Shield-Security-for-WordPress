<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StorageDir;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Base {

	use ModConsumer;

	public const MOD = ModCon::SLUG;

	private static $tempDir;

	private static $storageDir;

	protected function isTempDirAvailable() :bool {
		return !empty( $this->getTempDir() );
	}

	protected function getTempDir() :string {
		{ // TODO: remove this
			if ( !empty( self::$tempDir ) ) {
				return self::$tempDir;
			}
		}

		if ( empty( self::$storageDir ) ) {
			self::$storageDir = ( new StorageDir() )->setCon( $this->getCon() );
		}
		return self::$storageDir->getTempDir();
	}

	/**
	 * @deprecated 16.1.14
	 */
	private function generateNewDirName() :string {
		return $this->getCon()->cache_dir_handler->buildSubDir( 'ptguard-'.wp_generate_password( 16, false ) );
	}

	/**
	 * @throws \Exception
	 * @deprecated 16.1.14
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