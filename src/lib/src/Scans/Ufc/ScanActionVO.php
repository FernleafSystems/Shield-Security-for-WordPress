<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;

/**
 * @property string[] $scan_dirs
 */
class ScanActionVO extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScanActionVO {

	const QUEUE_GROUP_SIZE_LIMIT = 100;
}