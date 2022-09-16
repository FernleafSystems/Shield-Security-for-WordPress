<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\{
	ActionData,
	Actions\TrafficLogTableAction
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForTraffic;

class TrafficLogTable extends BasePluginAdminPage {

	const SLUG = 'page_admin_plugin_traffic_log_table';

	protected function getDefaults() :array {
		return [
			'primary_mod_slug' => 'traffic',
			'template'         => '/wpadmin_pages/insights/plugin_admin/table_traffic.twig',
		];
	}

	protected function getRenderData() :array {
		/** @var Options $opts */
		$opts = $this->primary_mod->getOptions();
		return [
			'ajax'    => [
				'traffictable_action' => ActionData::BuildJson( TrafficLogTableAction::SLUG ),
			],
			'flags'   => [
				'is_enabled' => $opts->isTrafficLoggerEnabled(),
			],
			'hrefs'   => [
				'please_enable' => $this->primary_mod->getUrl_DirectLinkToOption( 'enable_logger' ),
			],
			'strings' => [
			],
			'vars'    => [
				'datatables_init' => ( new ForTraffic() )
					->setMod( $this->primary_mod )
					->build()
			],
		];
	}
}