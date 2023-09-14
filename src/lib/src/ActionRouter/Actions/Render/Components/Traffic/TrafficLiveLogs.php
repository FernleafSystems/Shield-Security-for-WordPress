<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs\LoadRequestLogs;

class TrafficLiveLogs extends BaseRender {

	public const SLUG = 'render_traffic_live_logs';
	public const TEMPLATE = '/wpadmin/components/live_log.twig';

	protected function getRenderData() :array {

		$logLoader = new LoadRequestLogs();
		$logLoader->limit = 200;
		$logLoader->offset = 0;
		$logLoader->order_by = 'id';
		$logLoader->order_dir = 'DESC';
		return [
			'vars' => [
				'logs' => $logLoader->select(),
			]
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'current_scan',
			'remaining_scans',
			'progress',
		];
	}
}