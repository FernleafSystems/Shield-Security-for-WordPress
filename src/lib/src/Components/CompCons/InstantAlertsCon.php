<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Enum\EnumModules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class InstantAlertsCon {

	use ExecOnce;
	use PluginControllerConsumer;

	private $alerts;

	protected function canRun() :bool {
		return self::con()->comps->opts_lookup->isModEnabled( EnumModules::PLUGIN );
	}

	protected function run() {
		foreach ( $this->getAlerts() as $alert ) {
			$alert->execute();
		}
	}

	/**
	 * @return InstantAlerts\InstantAlertBase[]
	 */
	private function getAlerts() :array {
		if ( $this->alerts === null ) {
			$this->alerts = [];

			$alertOptions = \array_filter( \array_keys( self::con()->cfg->configuration->options ), function ( string $key ) {
				return \str_starts_with( $key, 'instant_alert_' );
			} );

			foreach ( $alertOptions as $alertKey ) {
				if ( self::con()->opts->optGet( $alertKey ) !== 'disabled' ) {
					/** @var ?InstantAlerts\InstantAlertBase|string $alert */
					$alert = $this->enum()[ \str_replace( 'instant_alert_', '', $alertKey ) ] ?? null;
					if ( !empty( $alert ) ) {
						$this->alerts[ $alertKey ] = new $alert();
					}
				}
			}
		}
		return $this->alerts;
	}

	private function enum() :array {
		return [
			'shield_deactivated' => InstantAlerts\InstantAlertShieldDeactivated::class,
			'admins'             => InstantAlerts\InstantAlertAdmins::class,
			'filelocker'         => InstantAlerts\InstantAlertFileLocker::class,
			'vulnerabilities'    => InstantAlerts\InstantAlertVulnerabilities::class,
		];
	}
}