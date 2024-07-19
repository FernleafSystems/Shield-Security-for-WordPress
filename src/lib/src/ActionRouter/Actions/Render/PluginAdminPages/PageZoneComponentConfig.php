<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Navigation\BuildBreadCrumbs;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\GetOptionsForZoneComponents;

class PageZoneComponentConfig extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_zone_component_config';

	protected function getRenderData() :array {
		$con = self::con();
		$component = $con->comps->zones->getZoneComponent( $this->zoneComponentSlug() );
		return [
			'content' => [
				'options_form' => $con->action_router->render( OptionsFormFor::class, [
					'options' => ( new GetOptionsForZoneComponents() )->run( [ $component::Slug() ] ),
				] ),
			],
			'imgs'    => [
				'inner_page_title_icon' => $con->svgs->raw( 'gear' ),
			],
			'strings' => [
				'inner_page_title'    => $component->title(),
				'inner_page_subtitle' => $component->subtitle(),
			],
		];
	}

	private function zoneComponentSlug() :string {
		return $this->action_data[ 'nav_sub' ];
	}

	/**
	 * Must manually build breadcrumbs for dynamic loaded config.
	 */
	protected function getBreadCrumbs() :array {
		$crumbs = parent::getBreadCrumbs();
		if ( empty( $crumbs ) ) {
			$crumbs = ( new BuildBreadCrumbs() )->for( PluginNavs::NAV_ZONES, $this->zoneComponentSlug() );
		}
		return $crumbs;
	}
}