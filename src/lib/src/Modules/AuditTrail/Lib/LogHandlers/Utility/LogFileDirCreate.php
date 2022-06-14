<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LogFileDirCreate {

	use ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function run() :string {
		$FS = Services::WpFs();

		$cacheDir = $this->getCon()->cache_dir_handler->dir();
		if ( empty( $cacheDir ) ) {
			throw new \Exception( "Plugin TMP Dir is unavailable." );
		}

		$theLogsDir = null;
		foreach ( $FS->getAllFilesInDir( $cacheDir, true ) as $possibleDir ) {
			$possibleFullPath = path_join( $cacheDir, $possibleDir );
			if ( strpos( basename( $possibleDir ), 'logs-' ) === 0 && $FS->isDir( $possibleDir ) ) {
				$theLogsDir = $possibleFullPath;
				break;
			}
		}

		if ( empty( $theLogsDir ) ) {
			$theLogsDir = path_join( $cacheDir, str_replace( '.', '', uniqid( 'logs-', true ) ) );
			$FS->mkdir( $theLogsDir );
		}

		if ( !$FS->isDir( $theLogsDir ) ) {
			throw new \Exception( "Couldn't create the logs dir." );
		}

		return $theLogsDir;
	}
}