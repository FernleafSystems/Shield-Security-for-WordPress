<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanActionConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class BuildFileMap
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf
 */
class BuildFileMap {

	use ScanActionConsumer;

	/**
	 * @return string[]
	 */
	public function build() {
		$aFiles = [];

		$oHashes = Services::CoreFileHashes();
		if ( $oHashes->isReady() ) {
			foreach ( array_keys( $oHashes->getHashes() ) as $sFragment ) {
				// To reduce noise, we exclude plugins and themes (by default)
				if ( strpos( $sFragment, 'wp-content/' ) === 0 ) {
					continue;
				}
				$aFiles[] = wp_normalize_path( path_join( ABSPATH, $sFragment ) );
			}
		}
		return $aFiles;
	}
}