<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Dashboard\DashboardViewPreference;

class DashboardViewToggle extends SecurityAdminBase {

	public const SLUG = 'dashboard_view_toggle';

	protected function exec() {
		( new DashboardViewPreference() )->setCurrent( (string)( $this->action_data[ 'view' ] ?? '' ) );

		$this->response()->action_response_data = [
			'success' => true,
		];
		$this->response()->next_step = [
			'type' => 'redirect',
			'url'  => self::con()->plugin_urls->adminRefererOrHome(),
		];
	}
}

