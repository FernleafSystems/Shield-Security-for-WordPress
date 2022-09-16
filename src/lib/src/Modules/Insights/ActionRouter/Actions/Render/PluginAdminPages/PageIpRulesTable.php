<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\IpRulesTableAction;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForIpRules;

class PageIpRulesTable extends BasePluginAdminPage {

	const SLUG = 'admin_plugin_page_ip_rules_table';

	protected function getDefaults() :array {
		return [
			'primary_mod_slug' => 'ips',
			'template'         => '/wpadmin_pages/insights/ips/ip_rules.twig',
		];
	}

	protected function getRenderData() :array {
		return [
			'ajax' => [
				'table_action' => ActionData::BuildJson( IpRulesTableAction::SLUG ),
			],
			'vars' => [
				'datatables_init' => ( new ForIpRules() )
					->setMod( $this->primary_mod )
					->build()
			],
		];
	}
}