<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\DashboardLiveMonitorPreference;

class DashboardLiveMonitorSetState extends SecurityAdminBase {

	public const SLUG = 'dashboard_live_monitor_set_state';

	protected function exec() {
		$collapsedRaw = $this->action_data[ 'is_collapsed' ] ?? $this->action_data[ 'collapsed' ] ?? false;
		$isCollapsed = \filter_var( $collapsedRaw, \FILTER_VALIDATE_BOOLEAN );

		$pref = new DashboardLiveMonitorPreference();
		$pref->setCollapsed( $isCollapsed );

		$this->response()
			 ->setPayload( [
				 'page_reload'  => false,
				 'is_collapsed' => $pref->isCollapsed(),
			 ] )
			 ->setPayloadSuccess( true );
	}
}
