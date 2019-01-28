<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\WpVulnDb\WpVulnVO;

/**
 * Class ResultItem
 * @property string slug
 * @property string context
 * @property int    wpvuln_id
 * @property array  wpvuln_vo
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv
 */
class ResultItem extends Base\BaseResultItem {

	const SCAN_RESULT_TYPE = 'wpv';

	/**
	 * @return string
	 */
	public function generateHash() {
		return md5( $this->slug.$this->wpvuln_id );
	}

	/**
	 * @return WpVulnVO
	 */
	public function getWpVulnVo() {
		return ( new WpVulnVO() )->applyFromArray( $this->wpvuln_vo );
	}
}