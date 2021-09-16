<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LogFileDirCreate {

	use ModConsumer;

	public function run() :string {
		$FS = Services::WpFs();
		$baseDir = $this->getCon()->getPluginCachePath();
		if ( empty( $baseDir ) ) {
			throw new \Exception( "Plugin TMP Dir is unavailable." );
		}

		$theLogsDir = null;
		foreach ( $FS->getAllFilesInDir( $baseDir, true ) as $possibleDir ) {
			$possibleFullPath = path_join( $baseDir, $possibleDir );
			if ( strpos( basename( $possibleDir ), 'logs-' ) === 0 && $FS->isDir( $possibleDir ) ) {
				$theLogsDir = $possibleFullPath;
				break;
			}
		}

		if ( empty( $theLogsDir ) ) {
			$theLogsDir = path_join( $baseDir, str_replace( '.', '', uniqid( 'logs-', true ) ) );
			$FS->mkdir( $theLogsDir );
		}

		if ( !$FS->isDir( $theLogsDir ) ) {
			throw new \Exception( "Couldn't create the logs dir." );
		}

		return $theLogsDir;
	}
}