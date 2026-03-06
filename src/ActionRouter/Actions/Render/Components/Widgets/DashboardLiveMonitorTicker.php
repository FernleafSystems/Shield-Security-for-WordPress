<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Traffic\LiveLogRowsBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LoadLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\{
	HighValueEvents
};

class DashboardLiveMonitorTicker extends OverviewBase {

	public const SLUG = 'render_dashboard_live_monitor_ticker';
	public const TEMPLATE = '/wpadmin/components/traffic/live_logs.twig';

	protected function getRenderData() :array {
		$records = [];

		$limit = (int)( $this->action_data[ 'limit' ] ?? 12 );
		$limit = \min( 30, \max( 1, $limit ) );

		$eventSlugs = ( new HighValueEvents() )->forDashboardTicker();
		if ( !empty( $eventSlugs ) ) {
			$records = $this->loadRecords( $eventSlugs, $limit );
		}

		return [
			'vars' => [
				'rows'      => ( new LiveLogRowsBuilder() )->buildActivityRows( $records ),
				'empty'     => __( 'No recent WordPress activity events yet.', 'wp-simple-firewall' ),
				'latest_id' => empty( $records ) ? 0 : (int)\reset( $records )->id,
			]
		];
	}

	/**
	 * @return LogRecord[]
	 */
	private function loadRecords( array $eventSlugs, int $limit ) :array {
		$loader = new LoadLogs();
		$loader->limit = $limit;
		$loader->offset = 0;
		$loader->order_by = 'id';
		$loader->order_dir = 'DESC';
		$loader->wheres = [
			\sprintf( "`log`.`event_slug` IN ('%s')", \implode( "','", \array_map( 'esc_sql', $eventSlugs ) ) ),
		];

		return \array_values( $loader->run() );
	}
}
