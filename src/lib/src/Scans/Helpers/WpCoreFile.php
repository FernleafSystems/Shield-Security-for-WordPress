<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg;

/**
 * Class WpCoreFileDownload
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers
 */
class WpCoreFile {

	const URL_WP_CORE = 'https://core.svn.wordpress.org';
	const URL_WP_CORE_IL8N = 'https://i18n.svn.wordpress.org';
	const URL_CP_CORE = 'https://raw.githubusercontent.com/ClassicPress/ClassicPress-release';
	const URL_CP_CORE_IL8N = 'https://raw.githubusercontent.com/ClassicPress/ClassicPress-release';

	/**
	 * @var string
	 */
	protected $sVersion;

	/**
	 * @param string $sPath
	 * @return bool
	 */
	public function replace( $sPath ) {
		$bSuccess = false;
		if ( Services::CoreFileHashes()->isCoreFile( $sPath ) ) {
			$oFiles = Services::WpGeneral()->isClassicPress() ? new WpOrg\Cp\Files() : new WpOrg\Wp\Files();
			try {
				$oFiles->replaceFileFromVcs( $sPath );
				$bSuccess = true;
			}
			catch ( \InvalidArgumentException $e ) {
			}
		}
		return $bSuccess;
	}

	/**
	 * @param string $sPath
	 * @param bool   $bUseLocale
	 * @return string - path to downloaded file
	 * @throws \InvalidArgumentException
	 */
	public function download( $sPath, $bUseLocale = true ) {
		$oHashes = Services::CoreFileHashes();
		if ( !$oHashes->isCoreFile( $sPath ) ) {
			throw new \InvalidArgumentException( sprintf( 'Core file "%s" is not an official WordPress core file.', $sPath ) );
		}

		$sLocale = Services::WpGeneral()->getLocaleForChecksums();
		$bUseInternational = $bUseLocale && ( $sLocale != 'en_US' );

		$sTmpFile = download_url( $this->getFileUrl( $sPath, $bUseLocale ) );
		if ( $bUseInternational && empty( $sTmpFile ) ) {
			$sTmpFile = $this->download( $sPath, false );
		} // try international retrieval and if it fails, we resort to en_US.

		return ( !is_wp_error( $sTmpFile ) && Services::WpFs()->exists( $sTmpFile ) ) ? $sTmpFile : null;
	}

	/**
	 * @param string $sPath
	 * @param bool   $bUseLocale
	 * @return string
	 */
	protected function getFileUrl( $sPath, $bUseLocale ) {
		$oWp = Services::WpGeneral();
		$sLocale = $oWp->getLocaleForChecksums();
		$bUseInternational = $bUseLocale && ( $sLocale != 'en_US' );

		if ( Services::WpGeneral()->isClassicPress() ) {
			$sFileUrl = sprintf(
				'%s/%s/%s',
				$bUseInternational ? self::URL_CP_CORE : self::URL_CP_CORE_IL8N,
				$this->getVersion(),
				$sPath
			);
		}
		else {
			$sFileUrl = sprintf(
				'%s/tags/%s/%s',
				$bUseInternational ? self::URL_WP_CORE_IL8N.'/'.$sLocale : self::URL_WP_CORE,
				$this->getVersion(),
				( $bUseInternational ? 'dist/' : '' ).$sPath
			);
		}

		return $sFileUrl;
	}

	/**
	 * @return string
	 */
	public function getVersion() {
		return empty( $this->sVersion ) ? Services::WpGeneral()->getVersion() : $this->sVersion;
	}

	/**
	 * @param string $sVersion
	 * @return $this
	 */
	public function setVersion( $sVersion ) {
		$this->sVersion = $sVersion;
		return $this;
	}
}
