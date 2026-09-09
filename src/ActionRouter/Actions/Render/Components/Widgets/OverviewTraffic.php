<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\{
	LoadRequestLogs,
	LogRecord
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\Utility\RequestLogDisplayPathBuilder;
use FernleafSystems\Wordpress\Services\Services;

class OverviewTraffic extends OverviewBase {

	public const SLUG = 'render_widget_overview_traffic';
	public const TEMPLATE = '/wpadmin/components/widget/overview_traffic.twig';

	protected function getRenderData() :array {
		$logLoader = new LoadRequestLogs();
		$logLoader->limit = 10;
		$logLoader->order_by = 'created_at';
		$logLoader->order_dir = 'DESC';

		$logs = \array_map(
			fn( LogRecord $record ) :array => $this->buildLogRow( $record ),
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
				'logs' => \array_slice( $logs, 0, \min( 100, \max( 1, $this->action_data[ 'limit' ] ?? 5 ) ) ),
			],
		];
	}

	/**
	 * @return array{ip:string,ip_href:string,path:string,ago:string}
	 */
	protected function buildLogRow( LogRecord $record ) :array {
		return [
			'ip'      => $record->ip,
			'ip_href' => self::con()->plugin_urls->ipAnalysis( $record->ip ),
			'path'    => $this->truncate( ( new RequestLogDisplayPathBuilder() )->build( $record ) ),
			'ago'     => Services::Request()
								 ->carbon( true )
								 ->setTimestamp( $record->created_at )
								 ->diffForHumans()
		];
	}
}
