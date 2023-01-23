<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class ScanEnabledFileLockerWpconfig extends ScanEnabledFileLockerBase {

	public const FILE_LOCKER_FILE = 'wp-config.php';
	public const FILE_LOCKER_FILE_KEY = 'wpconfig';
	public const WEIGHT = 6;
}