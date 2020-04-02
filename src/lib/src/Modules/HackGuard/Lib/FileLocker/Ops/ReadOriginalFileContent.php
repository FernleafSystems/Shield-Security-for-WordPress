<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldSecurityApi\FileLocker\DecryptFile;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Encrypt\OpenSslEncryptVo;

/**
 * Class ReadOriginalFileContent
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops
 */
class ReadOriginalFileContent extends BaseOps {

	/**
	 * @param Databases\FileLocker\EntryVO $oRecord
	 * @return string
	 */
	public function run( $oRecord ) {
		try {
			$sContent = $this->useOriginalFile( $oRecord );
		}
		catch ( \Exception $oE ) {
			$oVO = ( new OpenSslEncryptVo() )->applyFromArray( json_decode( $oRecord->content, true ) );
			$sContent = ( new DecryptFile() )
				->setMod( $this->getMod() )
				->retrieve( $oVO, $oRecord->public_key_id );
		}
		return $sContent;
	}

	/**
	 * @param Databases\FileLocker\EntryVO $oLock
	 * @return string|null
	 * @throws \Exception
	 */
	private function useOriginalFile( Databases\FileLocker\EntryVO $oLock ) {
		$oFS = Services::WpFs();
		if ( empty( $oLock->detected_at ) && empty( $oLock->hash_current )
			 && $oFS->exists( $oLock->file ) ) {
			return $oFS->getFileContent( $oLock->file );
		}
		throw new \Exception( 'Cannot use original file' );
	}
}