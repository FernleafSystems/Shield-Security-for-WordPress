<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForSecurityRules;
use FernleafSystems\Wordpress\Services\Services;

class PageRulesManage extends PageRulesBase {

	public const SLUG = 'admin_plugin_page_rules_manage';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/rules_manage.twig';

	protected function getRenderData() :array {
		return Services::DataManipulation()->mergeArraysRecursive( parent::getRenderData(), [
			'strings' => [
				'create_custom_rule' => __( 'Create Custom Rule', 'wp-simple-firewall' ),
				'disable_all_rules'  => __( 'Disable All Rules', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'datatables_init' => ( new ForSecurityRules() )->build(),
			],
		] );
	}

	protected function getInnerPageTitle() :string {
		return __( 'Custom Rules Manager', 'wp-simple-firewall' );
	}

	protected function getInnerPageSubTitle() :string {
		return __( 'View, edit and remove custom security rules', 'wp-simple-firewall' );
	}
}