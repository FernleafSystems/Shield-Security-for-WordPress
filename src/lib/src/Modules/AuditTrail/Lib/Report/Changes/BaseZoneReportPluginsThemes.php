<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LogRecord;

abstract class BaseZoneReportPluginsThemes extends BaseZoneReport {

	protected function buildSummaryForLog( LogRecord $log ) :string {
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
				$text = sprintf( __( 'Upgraded: %s&rarr;%s', 'wp-simple-firewall' ),
					sprintf( '<code>%s</code>', $log->meta_data[ 'from' ] ?? '??' ),
					sprintf( '<code>%s</code>', $log->meta_data[ 'to' ] ?? '??' )
				);
				break;
			case 'plugin_downgraded':
			case 'theme_downgraded':
				$text = sprintf( __( 'Downgraded: %s&rarr;%s', 'wp-simple-firewall' ),
					sprintf( '<code>%s</code>', $log->meta_data[ 'from' ] ?? '??' ),
					sprintf( '<code>%s</code>', $log->meta_data[ 'to' ] ?? '??' )
				);
				break;
			default:
				$text = parent::buildSummaryForLog( $log );
				break;
		}
		return $text;
	}
}