<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Utilities\Data\Adapter\DynProperties;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanResults\Ops\Record;

/**
 * Class ResultItem
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base
 * @property string $hash
 * @property bool   $is_excluded
 * @property string $scan
 * @property bool   $repaired
 * @property string $repair_event_status
 */
class ResultItem {

	use DynProperties;

	/**
	 * @var Record
	 */
	public $VO;

	public function isReady() :bool {
		return false;
	}

	public function generateHash() :string {
		return md5( json_encode( $this->getRawData() ) );
	}

	public function getDescriptionForAudit() :string {
		return 'No description';
	}

	/**
	 * @return mixed
	 */
	public function getData() {
		return $this->data ?? $this->getRawData();
	}
}