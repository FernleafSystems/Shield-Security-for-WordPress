<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Realtime\Files;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\ReadDataFromFileEncrypted;

/**
 * Class Revert
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\WpConfig
 */
class Revert {

	/**
	 * @param string $sOriginalPath
	 * @param string $sBackupFilePath
	 * @param string $sPrivateKey
	 * @return bool
	 */
	public function run( $sOriginalPath, $sBackupFilePath, $sPrivateKey ) {
		$bSuccess = false;
		try {
			$sData = ( new ReadDataFromFileEncrypted() )->run( $sBackupFilePath, $sPrivateKey );
			if ( !empty( $sData ) ) {
				$bSuccess = Services::WpFs()->putFileContent( $sOriginalPath, $sData );
			}
		}
		catch ( \Exception $oE ) {
		}

		return $bSuccess;
	}
}