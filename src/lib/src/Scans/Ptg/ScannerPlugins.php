<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ScannerPlugins
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg
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