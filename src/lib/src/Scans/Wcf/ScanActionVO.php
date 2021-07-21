<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;

/**
 * Class ScanActionVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf
 * @property string $exclusions_missing_regex
 * @property string $exclusions_files_regex
 */
class ScanActionVO extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseFileScanActionVO {

	const QUEUE_GROUP_SIZE_LIMIT = 100;
}