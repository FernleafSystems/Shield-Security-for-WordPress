<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;

class ScanEnabledFileLockerWebconfig extends ScanEnabledFileLockerBase {

	public const FILE_LOCKER_FILE = 'web.config';
	public const FILE_LOCKER_FILE_KEY = 'root_webconfig';

	protected function isApplicable() :bool {
		return Services::Data()->isWindows();
	}
}