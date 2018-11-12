<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class BaseResultItem
 * @property bool is_excluded
 * @property int  repaired_at
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\WpCore
 */
class BaseResultItem {

	use StdClassAdapter;

	/**
	 * @return bool
	 */
	public function isReady() {
		return false;
	}

	/**
	 * @return string
	 */
	public function generateHash() {
		return md5( json_encode( $this->getRawDataAsArray() ) );
	}
}