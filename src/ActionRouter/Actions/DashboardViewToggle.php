<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Dashboard\DashboardViewPreference;

class DashboardViewToggle extends SecurityAdminBase {

	public const SLUG = 'dashboard_view_toggle';

	protected function exec() {
		$pref = new DashboardViewPreference();
		$pref->setCurrent( (string)( $this->action_data[ 'view' ] ?? '' ) );

		if ( self::con()->this_req->wp_is_ajax ) {
			// The in-page dashboard toggle persists preference without forcing a page reload.
			$this->response()->action_response_data = [
				'success'     => true,
				'page_reload' => false,
				'view'        => $pref->getCurrent(),
			];
			return;
		}

		$this->response()->action_response_data = [
			'success' => true,
		];
		$this->response()->next_step = [
			'type' => 'redirect',
			'url'  => self::con()->plugin_urls->adminRefererOrHome(),
		];
	}
}
