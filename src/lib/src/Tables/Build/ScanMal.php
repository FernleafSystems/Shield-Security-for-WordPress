<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * Class ScanMal
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class ScanMal extends ScanBase {

	/**
	 * @return Shield\Tables\Render\ScanMal
	 */
	protected function getTableRenderer() {
		return new Shield\Tables\Render\ScanMal();
	}
}