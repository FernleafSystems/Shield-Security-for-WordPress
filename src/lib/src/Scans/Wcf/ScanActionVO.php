<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScanActionVO;

/**
 * @property string $exclusions_missing_regex
 * @property string $exclusions_files_regex
 */
class ScanActionVO extends BaseScanActionVO {

	const QUEUE_GROUP_SIZE_LIMIT = 100;
}