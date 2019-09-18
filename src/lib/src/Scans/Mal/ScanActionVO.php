<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScanActionVO;

/**
 * Class ScanActionVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal
 * @property string[] $file_exts
 * @property string[] $scan_root_dir
 * @property string[] $paths_whitelisted
 * @property string[] $patterns_regex
 * @property string[] $patterns_simple
 */
class ScanActionVO extends BaseScanActionVO {

	const ITEM_STORAGE_LIMIT = 50;
}