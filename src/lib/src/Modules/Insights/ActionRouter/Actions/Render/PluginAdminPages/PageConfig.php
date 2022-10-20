<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\PluginAdminPages;

class PageConfig extends BasePluginAdminPage {

	const SLUG = 'admin_plugin_page_config';
	const PRIMARY_MOD = 'plugin';
	const TEMPLATE = '/wpadmin_pages/insights/plugin_admin/config.twig';

	public function __get( string $key ) {
		$value = parent::__get( $key );

		switch ( $key ) {
			case 'primary_mod_slug':
				$value = $this->action_data[ 'nav_sub' ];
				break;

			default:
				break;
		}

		return $value;
	}
}