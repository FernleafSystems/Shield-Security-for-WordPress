<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Zones\SecurityZone;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Navigation\BuildBreadCrumbs;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone\Firewall;

class PageZone extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_zone';

	protected function getPageContextualHrefs() :array {
		$URLs = self::con()->plugin_urls;
		$hrefs = [];
		switch ( $this->action_data[ 'zone_slug' ] ) {
			case Firewall::Slug():
				$hrefs[] = [
					'text' => __( 'View Activity Log', 'wp-simple-firewall' ),
					'href' => $URLs->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS ),
				];
				$hrefs[] = [
					'text' => __( 'View Request Log', 'wp-simple-firewall' ),
					'href' => $URLs->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS ),
				];
				break;
			default:
				break;
		}

		return $hrefs;
	}

	protected function getRenderData() :array {
		$con = self::con();
		$zonesCon = $con->comps->zones;
		$zone = $zonesCon->getZone( $this->action_data[ 'zone_slug' ] );
		return [
			'content' => [
				'options_form' => self::con()->action_router->render( SecurityZone::class, [
					'zone_slug' => $this->action_data[ 'zone_slug' ],
				] ),
			],
			'imgs'    => [
				'inner_page_title_icon' => self::con()->svgs->raw( 'sliders' ),
			],
			'strings' => [
				'inner_page_title'    => $zone->title(),
				'inner_page_subtitle' => $zone->subtitle(),
			],
		];
	}

	/**
	 * Must manually build breadcrumbs for dynamic loaded config.
	 */
	protected function getBreadCrumbs() :array {
		$crumbs = parent::getBreadCrumbs();
		if ( empty( $crumbs ) ) {
			$crumbs = ( new BuildBreadCrumbs() )->for( PluginNavs::NAV_ZONES, $this->action_data[ 'zone_slug' ] );
		}
		return $crumbs;
	}
}