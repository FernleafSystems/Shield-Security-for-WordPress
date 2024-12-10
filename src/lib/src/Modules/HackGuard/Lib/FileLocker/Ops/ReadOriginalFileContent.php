<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\FileLocker\DecryptFile;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Encrypt\OpenSslEncryptVo;

class ReadOriginalFileContent extends BaseOps {

	/**
	 * @throws \Exception
	 */
	public function run( FileLockerDB\Record $lock ) :string {
		try {
			$content = $this->useOriginalFile( $lock );
		}
		catch ( \Exception $e ) {
			$content = $this->useCacheAndApi( $lock );
		}
		return $content;
	}

	/**
	 * @throws \Exception
	 */
	private function useOriginalFile( FileLockerDB\Record $lock ) :string {
		$FS = Services::WpFs();
		if ( empty( $lock->detected_at ) && empty( $lock->hash_current ) && $FS->exists( $lock->path ) ) {
			return (string)$FS->getFileContent( $lock->path );
		}
		throw new \Exception( 'Cannot use original file' );
	}

	/**
	 * @throws \Exception
	 */
	private function useCacheAndApi( FileLockerDB\Record $lock ) :string {
		$cacheKey = 'file-content-'.$lock->id;
		$content = wp_cache_get( $cacheKey, self::con()->prefix( 'filelocker' ) );
		if ( !\is_string( $content ) ) {
			$decoded = \json_decode( $lock->content, true );
			$VO = ( new OpenSslEncryptVo() )->applyFromArray( \is_array( $decoded ) ? $decoded : [] );
			$content = ( new DecryptFile() )->retrieve( $VO, (int)$lock->public_key_id );
			if ( $content === null ) {
				throw new \Exception( 'There was a problem decrypting the file contents.' );
			}
			wp_cache_set( $cacheKey, $content, self::con()->prefix( 'filelocker' ), 5 );
		}
		return $content;
	}
}