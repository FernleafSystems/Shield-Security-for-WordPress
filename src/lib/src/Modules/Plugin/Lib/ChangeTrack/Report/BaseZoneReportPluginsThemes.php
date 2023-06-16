<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Report;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\LogRecord;
use FernleafSystems\Wordpress\Services\Services;

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
				$text = sprintf( __( 'Upgraded To v%s', 'wp-simple-firewall' ), $log->meta_data[ 'to' ] ?? '??' );
				break;
			default:
				$text = 'unknown event';
				break;
		}
		return $text;
	}

	protected function buildDetailsForLog( LogRecord $log ) :string {
		$WP = Services::WpGeneral();
		$version = $log->meta_data[ 'version' ] ?? '??';
		$user = Services::WpUsers()->getUserById( $log->meta_data[ 'uid' ] ?? 0 );
		$username = empty( $user ) ? __( 'Unknown', 'wp-simple-firewall' ) : $user->user_login;
		switch ( $log->event_slug ) {
			case 'plugin_activated':
			case 'theme_activated':
				$text = sprintf( '[%s] %s',
					$WP->getTimeStringForDisplay( $log->created_at, false ),
					sprintf( __( 'Activated (v%s) by %s from %s', 'wp-simple-firewall' ),
						$version, $username, $log->ip
					)
				);
				break;
			case 'plugin_deactivated':
				$text = sprintf( '[%s] %s',
					$WP->getTimeStringForDisplay( $log->created_at, false ),
					sprintf( __( 'Deactivated (v%s) by %s from %s', 'wp-simple-firewall' ),
						$version, $username, $log->ip
					)
				);
				break;
			case 'plugin_installed':
			case 'theme_installed':
				$text = sprintf( '[%s] %s',
					$WP->getTimeStringForDisplay( $log->created_at, false ),
					sprintf( __( 'Installed (v%s) by %s from %s', 'wp-simple-firewall' ),
						$version, $username, $log->ip
					)
				);
				break;
			case 'plugin_uninstalled':
			case 'theme_uninstalled':
				$text = sprintf( '[%s] %s',
					$WP->getTimeStringForDisplay( $log->created_at, false ),
					sprintf( __( 'Uninstalled (v%s) by %s from %s', 'wp-simple-firewall' ),
						$version, $username, $log->ip
					)
				);
				break;
			case 'plugin_upgraded':
			case 'theme_upgraded':
				$text = sprintf( '[%s] %s',
					$WP->getTimeStringForDisplay( $log->created_at, false ),
					sprintf( __( 'Upgraded from v%s to v%s, by %s from %s', 'wp-simple-firewall' ),
						$log->meta_data[ 'from' ] ?? ' ??',
						$log->meta_data[ 'to' ] ?? ' ??',
						$username, $log->ip
					)
				);
				break;
			default:
				$text = 'unknown event';
				break;
		}
		return $text;
	}
}