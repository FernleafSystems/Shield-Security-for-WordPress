<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

/**
 * @property string[] $file_exts
 * @property string[] $scan_root_dirs
 * @property string[] $paths_whitelisted
 * @property string[] $patterns_regex
 * @property string[] $patterns_raw
 * @property string[] $patterns_iraw
 * @property string[] $patterns_functions
 * @property string[] $patterns_keywords
 * @property string[] $valid_files
 * @property int      $max_file_size (bytes)
 */
class ScanActionVO extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScanActionVO {

	public const DEFAULT_SLEEP_SECONDS = 0.1;
}