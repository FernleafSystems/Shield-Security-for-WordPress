<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LogRecord;

class ZoneReportWordpress extends BaseZoneReport {

	protected function buildSummaryForLog( LogRecord $log ) :string {
		switch ( $log->event_slug ) {
			case 'core_updated':
				$text = sprintf( __( 'Core Upgraded: %s&rarr;%s', 'wp-simple-firewall' ),
					$log->meta_data[ 'from' ], $log->meta_data[ 'to' ] );
				break;
			case 'core_reinstalled':
				$text = sprintf( __( 'Core Reinstalled: v%s', 'wp-simple-firewall' ), $log->meta_data[ 'version' ] );
				break;
			case 'permalinks_structure':
				$text = sprintf( 'Permalinks Changed: %s&rarr;%s',
					sprintf( '<code>%s</code>', $log->meta_data[ 'from' ] ),
					sprintf( '<code>%s</code>', $log->meta_data[ 'to' ] )
				);
				break;
			case 'wp_option_admin_email':
				$text = sprintf( 'Site Email Changed: %s&rarr;%s',
					sprintf( '<code>%s</code>', $log->meta_data[ 'from' ] ),
					sprintf( '<code>%s</code>', $log->meta_data[ 'to' ] )
				);
				break;
			case 'wp_option_blogname':
				$text = sprintf( 'Title Changed: %s&rarr;%s',
					sprintf( '<code>%s</code>', $log->meta_data[ 'from' ] ),
					sprintf( '<code>%s</code>', $log->meta_data[ 'to' ] )
				);
				break;
			case 'wp_option_blogdescription':
				$text = sprintf( 'Tagline Changed: %s&rarr;%s',
					sprintf( '<code>%s</code>', $log->meta_data[ 'from' ] ),
					sprintf( '<code>%s</code>', $log->meta_data[ 'to' ] )
				);
				break;
			case 'wp_option_home':
				$text = sprintf( 'Home URL Changed: %s&rarr;%s',
					sprintf( '<code>%s</code>', $log->meta_data[ 'from' ] ),
					sprintf( '<code>%s</code>', $log->meta_data[ 'to' ] )
				);
				break;
			case 'wp_option_siteurl':
				$text = sprintf( 'Site URL Changed: %s&rarr;%s',
					sprintf( '<code>%s</code>', $log->meta_data[ 'from' ] ),
					sprintf( '<code>%s</code>', $log->meta_data[ 'to' ] )
				);
				break;
			case 'wp_option_default_role':
				$text = sprintf( 'Default User Role Changed: %s&rarr;%s',
					sprintf( '<code>%s</code>', $log->meta_data[ 'from' ] ),
					sprintf( '<code>%s</code>', $log->meta_data[ 'to' ] )
				);
				break;
			case 'wp_option_users_can_register':
				$text = sprintf( 'Can Users Register Changed: %s&rarr;%s',
					sprintf( '<code>%s</code>', $log->meta_data[ 'from' ] ),
					sprintf( '<code>%s</code>', $log->meta_data[ 'to' ] )
				);
				break;
			default:
				$text = parent::buildSummaryForLog( $log );
				break;
		}
		return $text;
	}

	public function getZoneName() :string {
		return __( 'WordPress' );
	}

	protected function getLoadLogsWheres() :array {
		return [
			sprintf( "(`log`.`event_slug` IN ('%s') OR `log`.`event_slug` LIKE 'wp_option_%%')", \implode( "','", [
				'core_updated',
				'core_reinstalled',
				'permalinks_structure',
			] ) ),
		];
	}

	protected function getNameForLog( LogRecord $log ) :string {
		return [
				   'core_updated'                 => __( 'WordPress Upgraded', 'wp-simple-firewall' ),
				   'core_reinstalled'             => __( 'WordPress Reinstalled', 'wp-simple-firewall' ),
				   'permalinks_structure'         => __( 'Permalinks', 'wp-simple-firewall' ),
				   'wp_option_admin_email'        => __( 'Site Admin Email', 'wp-simple-firewall' ),
				   'wp_option_blogname'           => __( 'Site Title', 'wp-simple-firewall' ),
				   'wp_option_blogdescription'    => __( 'Site Tagline', 'wp-simple-firewall' ),
				   'wp_option_home'               => __( 'Home URL', 'wp-simple-firewall' ),
				   'wp_option_siteurl'            => __( 'Site URL', 'wp-simple-firewall' ),
				   'wp_option_default_role'       => __( 'Default User Role', 'wp-simple-firewall' ),
				   'wp_option_users_can_register' => __( 'Anyone Can Register', 'wp-simple-firewall' ),
			   ][ $log->event_slug ] ?? parent::getNameForLog( $log );
	}

	protected function getUniqFromLog( LogRecord $log ) :string {
		return $log->event_slug;
	}
}