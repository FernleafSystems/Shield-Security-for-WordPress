<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\{
	LoadLogs,
	LogRecord
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\ActivityLogMessageBuilder;
use FernleafSystems\Wordpress\Services\Services;

class OverviewActivity extends OverviewBase {

	public const SLUG = 'render_widget_overview_activity';
	public const TEMPLATE = '/wpadmin/components/widget/overview_activity.twig';

	protected function getRenderData() :array {
		$logLoader = new LoadLogs();
		$logLoader->limit = 50;
		$logLoader->order_by = 'created_at';
		$logLoader->order_dir = 'DESC';
		$logs = \array_map(
			fn( LogRecord $log ) => [
				'message' => $this->truncate( ActivityLogMessageBuilder::Build( $log->event_slug, $log->meta_data ?? [], ' ' ) ),
				'ip'      => $log->ip,
				'ip_href' => self::con()->plugin_urls->ipAnalysis( $log->ip ),
				'ago'     => Services::Request()
									 ->carbon( true )
									 ->setTimestamp( $log->created_at )
									 ->diffForHumans()
			],
			$logLoader->run()
		);
		return [
			'flags'   => [
				'has_logs' => !empty( $logs ),
			],
			'strings' => [
				'no_logs' => __( 'There are no logs available yet.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'logs' => \array_slice( $logs, 0, \min( 100, \max( 1, $this->action_data[ 'limit' ] ?? 5 ) ) ),
			],
		];
	}
}
