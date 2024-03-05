<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LogRecord;
use FernleafSystems\Wordpress\Services\Services;

class ZoneReportThemes extends BaseZoneReportPluginsThemes {

	public function getZoneName() :string {
		return __( 'Themes' );
	}

	protected function getLinkForLog( LogRecord $log ) :array {
		return [
			'href' => Services::WpGeneral()->getAdminUrl_Themes(),
			'text' => __( 'Themes' ),
		];
	}

	protected function getNameForLog( LogRecord $log ) :string {
		$item = Services::WpThemes()->getThemeAsVo( $log->meta_data[ 'theme' ] );
		return empty( $item ) ? $log->meta_data[ 'name' ] ?? __( 'Unknown Name', 'wp-simple-firewall' ) : $item->Name;
	}

	protected function getUniqFromLog( LogRecord $log ) :string {
		return $log->meta_data[ 'theme' ];
	}

	protected function getLoadLogsWheres() :array {
		return [
			sprintf( "`log`.`event_slug` IN ('%s')", \implode( "','", [
				'theme_activated',
				'theme_installed',
				'theme_uninstalled',
				'theme_upgraded',
				'theme_downgraded',
			] ) ),
		];
	}
}