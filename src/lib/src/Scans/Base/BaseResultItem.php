<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class BaseResultItem
 * @property string hash
 * @property bool   is_excluded
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf
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

	/**
	 * @return mixed
	 */
	public function getData() {
		return isset( $this->data ) ? $this->data : $this->getRawDataAsArray();
	}
}