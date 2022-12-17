<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Reports\Builders;

class Constants {

	public const REPORT_TYPE_ALERT = 'alt';
	public const REPORT_TYPE_INFO = 'nfo';
	public const REPORTER_BUILDERS = [
		Builders\AlertFileLocker::class,
		Builders\AlertScanResults::class,
		Builders\AlertScanRepairs::class,
		Builders\InfoKeyStats::class,
	];
}