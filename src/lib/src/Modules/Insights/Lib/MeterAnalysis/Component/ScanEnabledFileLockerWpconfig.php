<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

class ScanEnabledFileLockerWpconfig extends ScanEnabledFileLockerBase {

	public const SLUG = 'scan_enabled_filelocker_wpconfig';
	public const FILE_LOCKER_FILE = 'wp-config.php';
	public const FILE_LOCKER_FILE_KEY = 'wpconfig';
}