<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * Class ScanActionVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg
 * @property string[] $file_exts
 * @property string   $hashes_base_path
 * @property int      $scan_depth
 */
class ScanActionVO extends Shield\Scans\Base\BaseScanActionVO {

	const ITEM_STORAGE_LIMIT = 1;
}