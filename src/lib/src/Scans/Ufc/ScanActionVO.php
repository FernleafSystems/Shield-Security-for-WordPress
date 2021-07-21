<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;

/**
 * Class ScanActionVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc
 * @property string[] $scan_dirs
 * @property string[] $exclusions
 */
class ScanActionVO extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseFileScanActionVO {

	const QUEUE_GROUP_SIZE_LIMIT = 100;
}