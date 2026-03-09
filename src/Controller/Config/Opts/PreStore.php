<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class PreStore {

	use PluginControllerConsumer;

	public function run() :void {
		( new OptionsCorrections() )->run();
		( new OptionSaveSideEffects() )->run();
		$this->pluginKeepers();
		$this->scanKeepers();
		$this->securityAdminKeepers();
	}

	private function pluginKeepers() :void {
		$opts = self::con()->opts;
		$comps = self::con()->comps;

		if ( $opts->optGet( 'ipdetect_at' ) === 0
			 || ( $opts->optChanged( 'visitor_address_source' ) && $opts->optGet( 'visitor_address_source' ) === 'AUTO_DETECT_IP' )
		) {
			$opts->optSet( 'ipdetect_at', 1 );
		}

		if ( $opts->optGet( 'instant_alert_filelocker' ) !== 'disabled' && !$comps->file_locker->isEnabled() ) {
			$opts->optSet( 'instant_alert_filelocker', 'disabled' );
		}
		if ( $opts->optGet( 'instant_alert_vulnerabilities' ) !== 'disabled' && !$comps->scans->WPV()->isEnabled() ) {
			$opts->optSet( 'instant_alert_vulnerabilities', 'disabled' );
		}

		if ( $comps->opts_lookup->enabledTelemetry() && $opts->optGet( 'tracking_permission_set_at' ) === 0 ) {
			$opts->optSet( 'tracking_permission_set_at', Services::Request()->ts() );
		}

		$tmp = $opts->optGet( 'preferred_temp_dir' );
		if ( !empty( $tmp ) && !Services::WpFs()->isAccessibleDir( $tmp ) ) {
			$opts->optSet( 'preferred_temp_dir', '' );
		}
	}

	private function scanKeepers() :void {
		$con = self::con();
		foreach ( $con->comps->scans->getAllScanCons() as $scanCon ) {
			if ( !$scanCon->isEnabled() ) {
				$scanCon->purge();
			}
		}
	}

	private function securityAdminKeepers() :void {
		self::con()->comps->whitelabel->verifyUrls();
	}
}
