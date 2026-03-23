<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

abstract class ActionsQueueDrillDownRenderBase extends DrillDownAjaxRenderBase {

	use BuildsActionsQueueLandingData;

	protected function promotedRenderDataKeys() :array {
		return [
			'bucket_selection',
			'healthy_heading_label',
			'empty_message',
			'active_sections',
			'healthy_sections',
			'group_selection',
			'detail_html',
			'selected_group',
			'landing_refresh',
		];
	}
}
