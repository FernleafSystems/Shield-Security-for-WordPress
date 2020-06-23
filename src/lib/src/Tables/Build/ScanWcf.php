<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * Class ScanWcf
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class ScanWcf extends ScanBase {

	/**
	 * @return Shield\Tables\Render\WpListTable\ScanWcf
	 */
	protected function getTableRenderer() {
		return new Shield\Tables\Render\WpListTable\ScanWcf();
	}
}