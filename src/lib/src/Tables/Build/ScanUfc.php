<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * Class ScanUfc
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class ScanUfc extends ScanBase {

	/**
	 * @return Shield\Tables\Render\ScanUfc
	 */
	protected function getTableRenderer() {
		return new Shield\Tables\Render\ScanUfc();
	}
}