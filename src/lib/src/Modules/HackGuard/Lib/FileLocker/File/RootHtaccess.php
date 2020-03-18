<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\File;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class WpConfig
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\File
 */
class RootHtaccess extends File {

	const MAX_DIR_LEVELS = 1;

	/**
	 * @return string
	 */
	protected function getMaxDirLevels() {
		$nMax = parent::getMaxDirLevels();
		if ( true ) {
			$nMax++;
		}
		return $nMax;
	}
}