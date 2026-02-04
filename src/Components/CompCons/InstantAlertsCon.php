<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Email\EmailVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class InstantAlertsCon {

	use ExecOnce;
	use PluginControllerConsumer;

	private ?array $alerts = null;

	protected function canRun() :bool {
		return self::con()->comps->opts_lookup->isPluginEnabled();
	}

	protected function run() {
		if ( empty( $this->getAlertHandlers() ) ) {
			if ( !empty( $this->getAlertsData() ) ) {
				$this->setAlertsData( [] );
			}
		}
		else {
			foreach ( $this->getAlertHandlers() as $alert ) {
				$alert->execute();
			}

			if ( !empty( $this->getAlertsData() ) ) {
				$hook = self::con()->prefix( 'instant_alerts_send' );
				if ( !wp_next_scheduled( $hook ) ) {
					wp_schedule_single_event( Services::Request()->ts() + 30, $hook );
				}

				add_action( $hook, function () {
					$this->sendAlerts();
				} );
			}
		}
	}

	private function sendAlerts() :void {
		$alertsData = $this->getAlertsData();
		$this->setAlertsData( [] );
		foreach ( $alertsData as $handlerClass => $alertGroupData ) {
			if ( !empty( $alertGroupData ) ) {
				foreach ( $this->getAlertHandlers() as $handler ) {
					if ( \is_a( $handler, $handlerClass ) ) {
						self::con()->email_con->sendVO(
							EmailVO::Factory(
								self::con()->comps->opts_lookup->getReportEmail(),
								sprintf( '%s: %s', __( 'Alert', 'wp-simple-firewall' ), $handler->alertTitle() ),
								self::con()->action_router->render( $handler->alertAction(), [ 'alert_data' => $alertGroupData ] )
							)
						);
					}
				}
			}
		}
	}

	/**
	 * @param InstantAlerts\Handlers\AlertHandlerBase|mixed $handler
	 */
	public function getAlertsDataFor( $handler ) :array {
		$all = $this->getAlertsData();
		$class = \get_class( $handler );
		if ( !isset( $all[ $class ] ) ) {
			$all[ $class ] = \array_fill_keys( $handler->alertDataKeys(), [] );
		}
		return $all[ $class ];
	}

	/**
	 * @param InstantAlerts\Handlers\AlertHandlerBase|mixed $handler
	 */
	public function updateAlertDataFor( $handler, array $alertGroupData ) :void {
		$dataForHandler = $this->getAlertsDataFor( $handler );
		foreach ( $alertGroupData as $type => $alertGroupDatum ) {
			$dataForHandler[ $type ] = \array_unique( \array_merge( $dataForHandler[ $type ] ?? [], $alertGroupDatum ) );
		}
		$dataForHandler = \array_filter( \array_intersect_key( $dataForHandler, \array_flip( $handler->alertDataKeys() ) ) );

		$this->setAlertsData( \array_merge( $this->getAlertsData(), [ \get_class( $handler ) => $dataForHandler ] ) );

		if ( $handler->isImmediateAlert() ) {
			$this->sendAlerts();
		}
	}

	/**
	 * @return InstantAlerts\Handlers\AlertHandlerBase[]
	 */
	private function getAlertHandlers() :array {
		if ( $this->alerts === null ) {
			$this->alerts = [];

			$alertOptions = \array_filter( \array_keys( self::con()->cfg->configuration->options ), function ( string $key ) {
				return \str_starts_with( $key, 'instant_alert_' );
			} );

			foreach ( $alertOptions as $alertKey ) {
				if ( self::con()->opts->optGet( $alertKey ) !== 'disabled' ) {
					/** @var ?InstantAlerts\Handlers\AlertHandlerBase|string $handler */
					$handler = $this->enumHandlers()[ \str_replace( 'instant_alert_', '', $alertKey ) ] ?? null;
					if ( !empty( $handler ) ) {
						$this->alerts[ $handler ] = new $handler();
					}
				}
			}
		}
		return $this->alerts;
	}

	private function enumHandlers() :array {
		return [
			'admins'             => InstantAlerts\Handlers\AlertHandlerAdmins::class,
			'filelocker'         => InstantAlerts\Handlers\AlertHandlerFileLocker::class,
			'vulnerabilities'    => InstantAlerts\Handlers\AlertHandlerVulnerabilities::class,
			'shield_deactivated' => InstantAlerts\Handlers\AlertHandlerShieldDeactivated::class,
		];
	}

	private function getAlertsData() :array {
		return self::con()->opts->optGet( 'instant_alerts_data' );
	}

	private function setAlertsData( array $data ) :void {
		self::con()->opts->optSet( 'instant_alerts_data', \array_intersect_key( $data, $this->getAlertHandlers() ) );
	}
}