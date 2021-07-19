<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseBuildFileMap;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanActionConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class BuildFileMap
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf
 */
class BuildFileMap extends BaseBuildFileMap {

	/**
	 * @return string[]
	 */
	public function build() :array {
		$files = [];

		$coreHashes = Services::CoreFileHashes();
		if ( $coreHashes->isReady() ) {
			foreach ( array_keys( $coreHashes->getHashes() ) as $fragment ) {
				// To reduce noise, we exclude plugins and themes (by default)
				if ( strpos( $fragment, 'wp-content/' ) === 0 ) {
					continue;
				}
				$files[] = wp_normalize_path( path_join( ABSPATH, $fragment ) );
			}
		}
		return $files;
	}
}