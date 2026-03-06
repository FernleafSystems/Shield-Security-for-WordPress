<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\LoadRequestLogs;

class TrafficLiveLogs extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_traffic_live_logs';
	public const TEMPLATE = '/wpadmin/components/traffic/live_logs.twig';

	protected function getRenderData() :array {
		$logLoader = new LoadRequestLogs();
		$logLoader->limit = (int)( $this->action_data[ 'limit' ] ?? 200 );
		$logLoader->offset = 0;
		$logLoader->order_by = 'id';
		$logLoader->order_dir = 'DESC';
		return [
			'vars' => [
				'rows'  => ( new LiveLogRowsBuilder() )->buildTrafficRows( $logLoader->select() ),
				'empty' => __( 'No live traffic entries are available yet.', 'wp-simple-firewall' ),
			]
		];
	}
}
