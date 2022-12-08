<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib;

class Constants {

	public const REPORT_TYPE_ALERT = 'alt';
	public const REPORT_TYPE_INFO = 'nfo';
	public const REPORTERS = [
		Reports\Reporters\FileLockerAlerts::class,
		Reports\Reporters\KeyStats::class,
		Reports\Reporters\ScanAlerts::class,
		Reports\Reporters\ScanRepairs::class,
	];
}