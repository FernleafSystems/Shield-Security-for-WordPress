<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LogRecord;
use FernleafSystems\Wordpress\Services\Services;

class ZoneReportPlugins extends BaseZoneReportPluginsThemes {

	protected function getLoadLogsWheres() :array {
		return [
			sprintf( "`log`.`event_slug` IN ('%s')", \implode( "','", [
				'plugin_activated',
				'plugin_deactivated',
				'plugin_installed',
				'plugin_uninstalled',
				'plugin_upgraded',
				'plugin_downgraded',
			] ) ),
		];
	}

	protected function getLinkForLog( LogRecord $log ) :array {
		return [
			'href' => Services::WpGeneral()->getAdminUrl_Plugins(),
			'text' => __( 'Plugins' ),
		];
	}

	protected function getNameForLog( LogRecord $log ) :string {
		$plugin = Services::WpPlugins()->getPluginAsVo( $log->meta_data[ 'plugin' ] );
		return empty( $plugin ) ? $log->meta_data[ 'name' ] ?? $log->meta_data[ 'plugin' ] : $plugin->Name;
	}

	protected function getUniqFromLog( LogRecord $log ) :string {
		return $log->meta_data[ 'plugin' ];
	}

	public function getZoneName() :string {
		return __( 'Plugins' );
	}
}