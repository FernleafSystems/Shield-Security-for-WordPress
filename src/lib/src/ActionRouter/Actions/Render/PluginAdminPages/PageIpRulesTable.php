<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\IpRulesTableAction;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForIpRules;

class PageIpRulesTable extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_ip_rules_table';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/ip_rules.twig';

	protected function getPageContextualHrefs() :array {
		$con = self::con();
		return [
			[
				'title'   => __( 'Create New IP Rule', 'wp-simple-firewall' ),
				'href'    => 'javascript:{}',
				'classes' => [ 'offcanvas_form_create_ip_rule' ],
			],
			[
				'title'    => __( 'Download IP Rules as CSV', 'wp-simple-firewall' ),
				'href'     => $con->plugin_urls->fileDownloadAsStream( 'ip_rules' ),
				'disabled' => !$con->isPremiumActive(),
			],
		];
	}

	protected function getPageContextualHrefs_Help() :array {
		return [
			'title'      => sprintf( '%s: %s', __( 'Help', 'wp-simple-firewall' ), __( 'IP Rules', 'wp-simple-firewall' ) ),
			'href'       => 'https://help.getshieldsecurity.com/article/212-ip-rules-section-how-to-use-ip-management-and-analysis-tool',
			'new_window' => true,
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