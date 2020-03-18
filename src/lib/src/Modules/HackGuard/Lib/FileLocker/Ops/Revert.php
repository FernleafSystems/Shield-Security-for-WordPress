<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker\EntryVO;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Revert
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\WpConfig
 */
class Revert {

	/**
	 * @param EntryVO $oRecord
	 * @return mixed
	 */
	public function run( $oRecord ) {
		return Services::WpFs()->putFileContent( $oRecord->file, base64_decode( $oRecord->content ) );
	}

//
//	public function run( $sOriginalPath, $sBackupFilePath, $sPrivateKey ) {
//		$bSuccess = false;
//		try {
//			$sData = ( new ReadDataFromFileEncrypted() )->run( $sBackupFilePath, $sPrivateKey );
//			if ( !empty( $sData ) ) {
//				$bSuccess = Services::WpFs()->putFileContent( $sOriginalPath, $sData );
//			}
//		}
//		catch ( \Exception $oE ) {
//		}#
//	}
}