<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Utilities\Data\Adapter\DynProperties;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO;

/**
 * Class BaseResultItem
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base
 * @property int     $record_id
 * @property string  $hash
 * @property bool    $is_excluded
 * @property string  $scan
 * @property bool    $repaired
 */
class ResultItem {

	use DynProperties;

	/**
	 * @var EntryVO
	 */
	public $VO;

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