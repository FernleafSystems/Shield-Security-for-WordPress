<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class WpCoreFileDownload
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers
 */
class WpCoreFileDownload {

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
	 * @param bool   $bUseLocale
	 * @return string
	 */
	public function run( $sPath, $bUseLocale = true ) {
		$sLocale = Services::WpGeneral()->getLocale( true );
		$bUseInternational = $bUseLocale && ( $sLocale != 'en_US' );

		$sContent = (string)Services::WpFs()->getUrlContent( $this->getFileUrl( $sPath, $bUseLocale ) );
		if ( $bUseInternational && empty( $sContent ) ) {
			$sContent = $this->run( $sPath, false );
		} // try international retrieval and if it fails, we resort to en_US.

		return $sContent;
	}

	/**
	 * @param string $sPath
	 * @param bool   $bUseLocale
	 * @return string
	 */
	protected function getFileUrl( $sPath, $bUseLocale ) {
		$oWp = Services::WpGeneral();
		$sLocale = $oWp->getLocale( true );
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
