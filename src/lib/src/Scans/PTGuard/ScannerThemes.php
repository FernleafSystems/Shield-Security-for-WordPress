<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\PTGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ScannerThemes
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\PTGuard
 */
class ScannerThemes extends ScannerBase {

	const CONTEXT = 'themes';

	/**
	 * @param string $sSlug
	 * @return string
	 */
	protected function getDirFromItemSlug( $sSlug ) {
		return Services::WpThemes()->getInstallationDir( $sSlug );
	}
}