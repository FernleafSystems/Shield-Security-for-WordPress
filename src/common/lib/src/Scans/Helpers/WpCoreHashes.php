<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class WpCoreHashes
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers
 */
class WpCoreHashes {

	/**
	 * @var array
	 */
	private $aHashes;

	/**
	 * @return array
	 */
	public function getHashes() {
		if ( !isset( $this->aHashes ) ) {
			$aHash = Services::WpGeneral()->getCoreChecksums();
			$this->aHashes = is_array( $aHash ) ? $aHash : array();
		}
		return $this->aHashes;
	}

	/**
	 * @param string $sFile
	 * @return string|null
	 */
	public function getFileHash( $sFile ) {
		$sNorm = $this->getFileFragment( $sFile );
		return $this->isCoreFile( $sNorm ) ? $this->getHashes()[ $sNorm ] : null;
	}

	/**
	 * @param string $sFile
	 * @return string
	 */
	protected function getFileFragment( $sFile ) {
		$sNorm = wp_normalize_path( $sFile );
		return path_is_absolute( $sFile ) ? str_replace( ABSPATH, '', $sNorm ) : $sNorm;
	}

	/**
	 * @param string $sFile
	 * @return string
	 */
	public function getAbsolutePathFromFragment( $sFile ) {
		$sNorm = wp_normalize_path( $sFile );
		if ( !path_is_absolute( $sNorm ) ) {
			if ( strpos( $sNorm, 'wp-content/' ) === 0 ) {
				$sNorm = path_join( WP_CONTENT_DIR, str_replace( 'wp-content/', '', $sNorm ) );
			}
			else {
				$sNorm = path_join( ABSPATH, $sNorm );
			}
		}
		return $sNorm;
	}

	/**
	 * @param string $sFile
	 * @return bool
	 */
	public function isCoreFile( $sFile ) {
		return in_array( $this->getFileFragment( $sFile ), $this->getHashes() );
	}

	/**
	 * @return bool
	 */
	public function isReady() {
		return ( count( $this->getHashes() ) > 0 );
	}
}
