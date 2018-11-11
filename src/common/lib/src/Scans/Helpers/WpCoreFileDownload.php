<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class WpCoreFileDownload
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers
 */
class WpCoreFileDownload {

	const URL_WP_CORE_SVN = 'https://core.svn.wordpress.org';
	const URL_WP_CORE_SVN_IL8N = 'https://i18n.svn.wordpress.org';

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

		$sFileUrl = sprintf(
			'%s/tags/%s/%s',
			$bUseInternational ? self::URL_WP_CORE_SVN_IL8N.'/'.$sLocale : self::URL_WP_CORE_SVN,
			$this->getVersion(),
			( $bUseInternational ? 'dist/' : '' ).$sPath
		);

		$sContent = (string)Services::WpFs()->getUrlContent( $sFileUrl );
		if ( $bUseInternational && empty( $sContent ) ) {
			$sContent = $this->run( $sPath, false );
		} // try international retrieval and if it fails, we resort to en_US.

		return $sContent;
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
