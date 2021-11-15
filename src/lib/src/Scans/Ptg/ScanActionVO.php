<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScanActionVO;

/**
 * @property string[] $file_exts
 */
class ScanActionVO extends BaseScanActionVO {

	const CONTEXT_PLUGINS = 'plugins';
	const CONTEXT_THEMES = 'themes';
	const QUEUE_GROUP_SIZE_LIMIT = 50;
}