<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Enum\EnumModules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class InstantAlertsCon {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return self::con()->comps->opts_lookup->isModEnabled( EnumModules::PLUGIN )
			   && \count( self::con()->opts->optGet( 'instant_alerts' ) ) > 0;
	}

	protected function run() {
		foreach ( \array_intersect_key( $this->enum(), \array_flip( self::con()->opts->optGet( 'instant_alerts' ) ) ) as $alert ) {
			/** @var InstantAlerts\InstantAlertBase|string $alert */
			( new $alert() )->execute();
		}
	}

	private function enum() :array {
		return [
			'admins'          => InstantAlerts\InstantAlertAdmins::class,
			'vulnerabilities' => InstantAlerts\InstantAlertVulnerabilities::class,
		];
	}
}