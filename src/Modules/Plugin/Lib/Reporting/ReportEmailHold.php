<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * For the first day after Shield is installed, scan-driven alert and report emails wait until someone chooses a
 * report email, so they don't go to the site admin email (often the site owner, not whoever installed Shield).
 * Held emails keep their pending data and go out once the hold ends; nothing else stores hold state.
 */
class ReportEmailHold {

	use PluginControllerConsumer;

	public const WINDOW = \DAY_IN_SECONDS;

	public function isActive() :bool {
		$lookup = self::con()->comps->opts_lookup;
		$installedAt = $lookup->getInstalledAt();
		return $installedAt > 0
			   && Services::Request()->ts() - $installedAt < self::WINDOW
			   && $lookup->getChosenReportEmail() === '';
	}

	public function explanation() :string {
		return __( "Shield is holding its first scan alerts until you choose where reports and alerts go. If you don't choose within a day of installing Shield, they go to the site admin email.", 'wp-simple-firewall' );
	}
}
