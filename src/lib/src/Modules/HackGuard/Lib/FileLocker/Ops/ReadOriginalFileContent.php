<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\FileLocker\DecryptFile;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Encrypt\OpenSslEncryptVo;

/**
 * Class ReadOriginalFileContent
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops
 */
class ReadOriginalFileContent extends BaseOps {

	/**
	 * @param Databases\FileLocker\EntryVO $oLock
	 * @return string
	 */
	public function run( $oLock ) {
		try {
			$sContent = $this->useOriginalFile( $oLock );
		}
		catch ( \Exception $oE ) {
			$sContent = $this->useCacheAndApi( $oLock );
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

	/**
	 * @param Databases\FileLocker\EntryVO $oLock
	 * @return string|null
	 */
	private function useCacheAndApi( Databases\FileLocker\EntryVO $oLock ) {
		$sCacheKey = 'file-content-'.$oLock->id;
		$sContent = wp_cache_get( $sCacheKey, $this->getCon()->prefix( 'filelocker' ) );
		if ( $sContent === false ) {
			$oVO = ( new OpenSslEncryptVo() )->applyFromArray( json_decode( $oLock->content, true ) );
			$sContent = ( new DecryptFile() )
				->setMod( $this->getMod() )
				->retrieve( $oVO, $oLock->public_key_id );
			wp_cache_set( $sCacheKey, $sContent, $this->getCon()->prefix( 'filelocker' ), 3 );
		}
		return $sContent;
	}
}