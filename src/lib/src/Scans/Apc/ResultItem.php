<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * Class ResultItem
 * @property string slug
 * @property string context
 * @property int    wpvuln_id
 * @property array  wpvuln_vo
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc
 */
class ResultItem extends Base\BaseResultItem {
	const SCAN_RESULT_TYPE = 'apc';
}