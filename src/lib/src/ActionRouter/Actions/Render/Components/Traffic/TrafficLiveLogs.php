<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\LoadRequestLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\Utility\ConvertLogsToFlatText;

class TrafficLiveLogs extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_traffic_live_logs';
	public const TEMPLATE = '/wpadmin/components/traffic/live_logs.twig';

	protected function getRenderData() :array {
		$logLoader = new LoadRequestLogs();
		$logLoader->limit = $this->action_data[ 'limit' ] ?? 200;
		$logLoader->offset = 0;
		$logLoader->order_by = 'id';
		$logLoader->order_dir = 'DESC';
		return [
			'vars' => [
				'logs' => ( new ConvertLogsToFlatText() )->convert( $logLoader->select(), true ),
			]
		];
	}
}