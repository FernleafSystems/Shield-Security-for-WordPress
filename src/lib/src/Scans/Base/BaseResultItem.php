<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Utilities\Data\Adapter\DynProperties;

/**
 * Class BaseResultItem
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base
 * @property string $hash
 * @property bool   $is_excluded
 * @property string $scan
 * @property bool   $repaired
 */
class BaseResultItem {

	use DynProperties;

	public function isReady() :bool {
		return false;
	}

	public function generateHash() :string {
		return md5( json_encode( $this->getRawData() ) );
	}

	/**
	 * @return mixed
	 */
	public function getData() {
		return $this->data ?? $this->getRawData();
	}
}