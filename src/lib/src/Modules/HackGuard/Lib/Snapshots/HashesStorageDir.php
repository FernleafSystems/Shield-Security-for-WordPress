<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class HashesStorageDir {

	use PluginControllerConsumer;

	public const SUFFIX_LENGTH = 16;

	private static $dir = null;

	public function getTempDir() :string {
		if ( \is_null( self::$dir ) ) {
			try {
				$dir = $this->locateTempDir();
			}
			catch ( \Exception $e ) {
				$dir = self::con()->cache_dir_handler->buildSubDir( 'ptguard-'.wp_generate_password( self::SUFFIX_LENGTH, false ) );
			}
			self::$dir = $dir;
		}
		return self::$dir;
	}

	/**
	 * @throws \Exception
	 */
	private function locateTempDir() :string {
		$FS = Services::WpFs();
		$dir = null;
		foreach ( $FS->getAllFilesInDir( self::con()->cache_dir_handler->dir() ) as $fileItem ) {
			if ( $FS->isDir( $fileItem ) && \preg_match( sprintf( '#^ptguard-[a-z0-9]{%s}$#i', self::SUFFIX_LENGTH ), \basename( $fileItem ) ) ) {
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