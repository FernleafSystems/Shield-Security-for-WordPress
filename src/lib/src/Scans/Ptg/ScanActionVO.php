<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

/**
 * Class ScanActionVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg
 * @property string[] $scan_root_dirs
 * @property string[] $file_exts
 */
class ScanActionVO extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseFileScanActionVO {

	const CONTEXT_PLUGINS = 'plugins';
	const CONTEXT_THEMES = 'themes';
	const QUEUE_GROUP_SIZE_LIMIT = 50;
}