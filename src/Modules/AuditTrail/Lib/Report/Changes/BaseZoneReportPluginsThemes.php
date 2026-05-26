<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LogRecord;

abstract class BaseZoneReportPluginsThemes extends BaseZoneReport {

	protected function buildSummaryLinesForLog( LogRecord $log ) :array {
		$version = $log->meta_data[ 'version' ] ?? ' ??';
		switch ( $log->event_slug ) {
			case 'plugin_activated':
			case 'theme_activated':
				$text = sprintf( '%s (v%s)', __( 'Activated', 'wp-simple-firewall' ), $version );
				break;
			case 'plugin_deactivated':
				$text = sprintf( '%s (v%s)', __( 'Deactivated', 'wp-simple-firewall' ), $version );
				break;
			case 'plugin_installed':
			case 'theme_installed':
				$text = sprintf( '%s (v%s)', __( 'Installed', 'wp-simple-firewall' ), $version );
				break;
			case 'plugin_uninstalled':
			case 'theme_uninstalled':
				$text = sprintf( '%s (v%s)', __( 'Uninstalled', 'wp-simple-firewall' ), $version );
				break;
			case 'plugin_upgraded':
			case 'theme_upgraded':
				/* translators: %1$s: old version, %2$s: new version */
				$text = sprintf( __( 'Upgraded: %1$s -> %2$s', 'wp-simple-firewall' ),
					$log->meta_data[ 'from' ] ?? '??',
					$log->meta_data[ 'to' ] ?? '??'
				);
				break;
			case 'plugin_downgraded':
			case 'theme_downgraded':
				/* translators: %1$s: old version, %2$s: new version */
				$text = sprintf( __( 'Downgraded: %1$s -> %2$s', 'wp-simple-firewall' ),
					$log->meta_data[ 'from' ] ?? '??',
					$log->meta_data[ 'to' ] ?? '??'
				);
				break;
			default:
				return parent::buildSummaryLinesForLog( $log );
		}
		return [ (string)$text ];
	}
}
