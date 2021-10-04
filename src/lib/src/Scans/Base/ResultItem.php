<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Utilities\Data\Adapter\DynProperties;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\ScanResultVO;

/**
 * @property string $hash
 * @property string $scan
 * @property bool   $deleted
 * @property bool   $repaired
 * @property string $repair_event_status
 */
class ResultItem {

	use DynProperties;

	/**
	 * @var ScanResultVO
	 */
	public $VO;

	public function __construct() {
	}

	public function generateHash() :string {
		return md5( json_encode( $this->getRawData() ) );
	}

	public function getDescriptionForAudit() :string {
		return 'No description';
	}
}