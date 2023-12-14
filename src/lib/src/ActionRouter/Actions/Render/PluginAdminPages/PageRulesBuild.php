<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

class PageRulesBuild extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_rules_build';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/rules_build.twig';

	protected function getRenderData() :array {
		return [
			'strings'=>[
				'title' => 'todo',
			]
		];
	}
}