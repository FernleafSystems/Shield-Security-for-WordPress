<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\IpRulesTableAction;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForIpRules;

class PageIpRulesTable extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_ip_rules_table';
	public const PRIMARY_MOD = 'ips';
	public const TEMPLATE = '/wpadmin_pages/plugin_admin/ip_rules.twig';

	protected function getRenderData() :array {
		$con = $this->getCon();
		return [
			'hrefs'   => [
				'inner_page_config' => [
					[
						'text' => __( 'Configure IP Blocking', 'wp-simple-firewall' ),
						'href' => $con->plugin_urls->offCanvasConfigRender( $this->primary_mod->getSlug() ),
					],
				],
			],
			'strings' => [
				'inner_page_title'    => __( 'Manage IP Rules', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'View and manage IP rules that block malicious visitors and bots.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'datatable_iprules' => wp_json_encode( [
					'ajax'       => [
						'table_action' => ActionData::Build( IpRulesTableAction::SLUG ),
					],
					'table_init' => ( new ForIpRules() )
						->setMod( $this->primary_mod )
						->buildRaw(),
				] ),
			]
		];
	}
}