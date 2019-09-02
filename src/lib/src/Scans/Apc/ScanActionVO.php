<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * Class ScanActionVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc
 * @property int $abandoned_limit
 */
class ScanActionVO extends Shield\Scans\Base\BaseScanActionVO {

	const ITEM_STORAGE_LIMIT = 3;
}