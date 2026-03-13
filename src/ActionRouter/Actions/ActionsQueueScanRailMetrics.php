<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueScanRailMetricsBuilder;

class ActionsQueueScanRailMetrics extends SecurityAdminBase {

	public const SLUG = 'actions_queue_scan_rail_metrics';

	protected function exec() {
		$this->response()
			 ->setPayload( ( new ActionsQueueScanRailMetricsBuilder() )->build( self::con()->comps->site_query->attention() ) )
			 ->setPayloadSuccess( true );
	}
}
