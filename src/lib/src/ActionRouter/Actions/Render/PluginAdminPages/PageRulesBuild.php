<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Services\Services;

class PageRulesBuild extends PageRulesBase {

	public const SLUG = 'admin_plugin_page_rules_build';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/rules_build.twig';

	protected function getPageContextualHrefs() :array {
		return [
			[
				'title' => __( 'Manage Rules', 'wp-simple-firewall' ),
				'href'  => self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_RULES, PluginNavs::SUBNAV_RULES_MANAGE ),
			],
		];
	}

	protected function getRenderData() :array {
		return Services::DataManipulation()->mergeArraysRecursive( parent::getRenderData(), [
		] );
	}

	protected function getInnerPageTitle() :string {
		return __( 'Build Custom Security Rules', 'wp-simple-firewall' );
	}

	protected function getInnerPageSubTitle() :string {
		return __( 'Create custom rules to meet all your security needs', 'wp-simple-firewall' );
	}
}