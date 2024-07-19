<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Navigation\BuildBreadCrumbs;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\GetOptionsForZoneComponents;

class PageConfigForZoneComponents extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_config_for_zone_components';

	protected function getRenderData() :array {
		$con = self::con();
		return [
			'content' => [
				'options_form' => $con->action_router->render( OptionsFormFor::class, [
					'options' => ( new GetOptionsForZoneComponents() )->run( $this->action_data[ 'zone_component_slugs' ] ),
				] ),
			],
			'imgs'    => [
				'inner_page_title_icon' => self::con()->svgs->raw( 'sliders' ),
			],
			'strings' => [
//				'inner_page_title'    => $modStrings[ 'name' ],
//				'inner_page_subtitle' => $modStrings[ 'subtitle' ],
			],
		];
	}

	/**
	 * Must manually build breadcrumbs for dynamic loaded config.
	 */
	protected function getBreadCrumbs() :array {
		if ( empty( $crumbs ) ) {
			$crumbs = ( new BuildBreadCrumbs() )->for( PluginNavs::NAV_ZONE_COMPONENTS, \current( $this->action_data[ 'zone_component_slugs' ] ) );
		}
		return $crumbs;
	}

	protected function getRequiredDataKeys() :array {
		return [
			'zone_component_slugs',
		];
	}
}