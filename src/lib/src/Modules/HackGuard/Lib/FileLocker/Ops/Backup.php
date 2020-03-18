<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Services;

/**
 * Class Backup
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\WpConfig
 */
class Backup {

	/**
	 * @param string $sOriginalPath
	 * @param string $sBackupPath
	 * @param string $sPubKey
	 * @return bool
	 * @throws \Exception
	 */
	public function run( $sOriginalPath, $sBackupPath, $sPubKey ) {
		$sCont = Services\Services::WpFs()->getFileContent( $sOriginalPath );
		if ( empty( $sCont ) ) {
			throw new \Exception( 'WP Config contents were empty' );
		}
		return ( new Services\Utilities\File\WriteDataToFileEncrypted() )->run( $sBackupPath, $sCont, $sPubKey );
	}
}