<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScanActionVO;

/**
 * @property int      $realtime_scan_last_at
 * @property string[] $file_exts
 * @property string[] $scan_root_dirs
 * @property string[] $paths_whitelisted
 * @property string[] $patterns_regex
 * @property string[] $patterns_raw
 * @property string[] $patterns_iraw
 * @property string[] $patterns_functions
 * @property string[] $patterns_keywords
 * @property string[] $valid_files
 */
class ScanActionVO extends BaseScanActionVO {

	public const DEFAULT_SLEEP_SECONDS = 0.1;
}