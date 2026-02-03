<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

class PageDynamicLoad extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_dynamic';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/dynamic.twig';
}