<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon {

	public const SLUG = \FernleafSystems\Wordpress\Plugin\Shield\Enum\EnumModules::SCANS;

	/**
	 * @deprecated 19.2
	 */
	public function getFileLocker() :Lib\FileLocker\FileLockerController {
		return self::con()->comps->file_locker;
	}

	/**
	 * @deprecated 19.2
	 */
	public function getScansCon() :Scan\ScansController {
		return self::con()->comps->scans;
	}

	/**
	 * @deprecated 19.2
	 */
	public function getScanQueueController() :Scan\Queue\Controller {
		return self::con()->comps->scans_queue;
	}
}