<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\PluginAdminPages;

class PageConfig extends BasePluginAdminPage {

	const SLUG = 'admin_plugin_page_config';

	public function __get( string $key ) {
		$value = parent::__get( $key );

		switch ( $key ) {
			case 'primary_mod_slug':
				$value = $this->nav_sub;
				break;

			default:
				break;
		}

		return $value;
	}

	protected function getDefaults() :array {
		return [
			'primary_mod_slug' => 'plugin',
			'template'         => '/wpadmin_pages/insights/plugin_admin/config.twig',
		];
	}

	protected function getRenderData() :array {
		return $this->primary_mod->getUIHandler()->getBaseDisplayData();
	}
}