<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Components;

class Constants {

	public const REPORT_TYPE_ALERT = 'alt';
	public const REPORT_TYPE_INFO = 'nfo';
	public const COMPONENT_REPORT_BUILDERS = [
		Components\AlertFileLocker::class,
		Components\AlertScanResults::class,
		Components\AlertScanRepairs::class,
		Components\InfoKeyStats::class,
	];
	public const REPORT_CONTEXT_AUTO = 'auto';
	public const REPORT_CONTEXT_AD_HOC = 'ad_hoc';
}