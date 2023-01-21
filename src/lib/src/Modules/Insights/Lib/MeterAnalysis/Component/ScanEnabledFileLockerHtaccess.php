<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

class ScanEnabledFileLockerHtaccess extends ScanEnabledFileLockerBase {

	public const FILE_LOCKER_FILE = '.htaccess';
	public const FILE_LOCKER_FILE_KEY = 'htaccess';
}