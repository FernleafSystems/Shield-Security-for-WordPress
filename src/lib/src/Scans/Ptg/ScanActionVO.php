<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * Class ScanActionVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg
 * @property string[][] $existing_hashes_plugins - keys are slugs; values are hashes
 * @property string[][] $existing_hashes_themes
 * @property string[]   $file_exts
 * @property int        $scan_depth
 */
class ScanActionVO extends Shield\Scans\Base\BaseScanActionVO {

}