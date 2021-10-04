<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

/**
 * @property int $abandoned_limit
 */
class ScanActionVO extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScanActionVO {

	const QUEUE_GROUP_SIZE_LIMIT = 3;
}