<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\IpRulesTableAction;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForIpRules;

class PageIpRulesTable extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_ip_rules_table';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/ip_rules.twig';

	protected function getPageContextualHrefs() :array {
		return [
			[
				'text' => __( 'Create New IP Rule', 'wp-simple-firewall' ),
				'href' => 'javascript:{iCWP_WPSF_OffCanvas.renderIpRuleAddForm()}',
			],
			[
				'text' => __( 'Configure IP Blocking', 'wp-simple-firewall' ),
				'href' => self::con()->plugin_urls->offCanvasConfigRender( self::con()->getModule_IPs()->cfg->slug ),
			],
		];
	}

	protected function getRenderData() :array {
		return [
			'strings' => [
				'inner_page_title'    => __( 'Manage Rules', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'View and manage IP rules that block malicious visitors and bots.', 'wp-simple-firewall' ),
			],
			'imgs'    => [
				'inner_page_title_icon' => self::con()->svgs->raw( 'diagram-3' ),
			],
			'vars'    => [
				'datatable_iprules' => wp_json_encode( [
					'ajax'       => [
						'table_action' => ActionData::Build( IpRulesTableAction::class ),
					],
					'table_init' => ( new ForIpRules() )->buildRaw(),
				] ),
			]
		];
	}
}