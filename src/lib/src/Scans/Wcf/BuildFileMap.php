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

		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		$oHashes = Services::CoreFileHashes();
		if ( $oHashes->isReady() ) {
			$this->preBuild();
			foreach ( array_keys( $oHashes->getHashes() ) as $sFragment ) {
				// To reduce noise, we exclude plugins and themes (by default)
				if ( strpos( $sFragment, 'wp-content/' && $oAction->is_exclude_plugins_themes ) === 0 ) {
					continue;
				}
				$aFiles[] = wp_normalize_path( path_join( ABSPATH, $sFragment ) );
			}
		}
		return $aFiles;
	}

	protected function preBuild() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		if ( !isset( $oAction->is_exclude_plugins_themes ) ) {
			$oAction->is_exclude_plugins_themes = true;
		}
	}
}