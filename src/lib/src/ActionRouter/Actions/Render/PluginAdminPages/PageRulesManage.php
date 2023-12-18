<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Services\Services;

class PageRulesManage extends PageRulesBase {

	public const SLUG = 'admin_plugin_page_rules_manage';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/rules_manage.twig';

	protected function getRenderData() :array {
		$con = self::con();
		return Services::DataManipulation()->mergeArraysRecursive(
			parent::getRenderData(),
			[
			]
		);
	}

	protected function getInnerPageTitle() :string {
		return __( 'Manage Custom Rules', 'wp-simple-firewall' );
	}

	protected function getInnerPageSubTitle() :string {
		return __( 'View, edit and remove custom security rules', 'wp-simple-firewall' );
	}
}