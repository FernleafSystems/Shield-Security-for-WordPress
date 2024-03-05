<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\{
	LoadRequestLogs,
	LogRecord
};
use FernleafSystems\Wordpress\Services\Services;

class OverviewTraffic extends OverviewBase {

	public const SLUG = 'render_widget_overview_traffic';
	public const TEMPLATE = '/wpadmin/components/widget/overview_traffic.twig';

	protected function getRenderData() :array {
		$logLoader = new LoadRequestLogs();
		$logLoader->limit = 5;
		$logLoader->order_by = 'created_at';
		$logLoader->order_dir = 'DESC';

		$logs = \array_map(
			function ( LogRecord $record ) {
				$path = $record->path;
				if ( !empty( $record->meta[ 'query' ] ) ) {
					$path .= '?'.$record->meta[ 'query' ];
				}
				return [
					'ip'   => $record->ip,
					'path' => $this->truncate( $path ),
					'ago'  => Services::Request()
									  ->carbon( true )
									  ->setTimestamp( $record->created_at )
									  ->diffForHumans()
				];
			},
			$logLoader->select()
		);

		return [
			'flags'   => [
				'has_logs' => !empty( $logs ),
			],
			'strings' => [
				'no_logs' => __( 'There are no logs available yet.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'logs' => $logs,
			],
		];
	}
}