<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\PTGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ScannerPlugins
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\PTGuard
 */
class ScannerPlugins extends ScannerBase {

	const CONTEXT = 'plugins';

	/**
	 * @param string $sSlug
	 * @return string
	 */
	protected function getDirFromItemSlug( $sSlug ) {
		return Services::WpPlugins()->getInstallationDir( $sSlug );
	}
}