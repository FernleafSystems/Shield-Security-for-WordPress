<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\IpRulesTableAction;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForIpRules;

class PageIpRulesTable extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_ip_rules_table';
	public const PRIMARY_MOD = 'ips';
	public const TEMPLATE = '/wpadmin_pages/insights/ips/ip_rules.twig';

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