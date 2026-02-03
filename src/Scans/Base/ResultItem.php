<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\ScanResultVO;

/**
 * @property bool   $deleted
 * @property bool   $repaired
 * @property string $repair_event_status
 * @property bool   $auto_filter
 */
class ResultItem extends DynPropertiesClass {

	/**
	 * @var ScanResultVO
	 */
	public $VO;

	public function getDescriptionForAudit() :string {
		return 'No description';
	}

	public function getStatusForHuman() :array {
		return [ 'No Status' ];
	}
}