<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components\DashboardWidget;
use FernleafSystems\Wordpress\Services\Services;

class PluginDashboardWidgetRender extends PluginBase {

	use Traits\SecurityAdminNotRequired;

	const SLUG = 'dashboard_widget_render';

	/**
	 * @inheritDoc
	 */
	protected function exec() {
		$this->response()->action_response_data = [
			'success' => true,
			'html'    => ( new DashboardWidget() )
				->setMod( $this->getMod() )
				->render( (bool)Services::Request()->post( 'refresh' ) )
		];
	}
}