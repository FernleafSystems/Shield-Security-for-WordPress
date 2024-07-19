<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\CrowdsecResetEnrollment;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Zones\SecurityZone;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SecurityAdminRemove;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Navigation\BuildBreadCrumbs;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone\{
	Firewall,
	Ips,
	Scans,
	Secadmin,
};

class PageZone extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_zone';

	protected function getPageContextualHrefs() :array {
		$con = self::con();
		$URLs = $con->plugin_urls;
		$hrefs = [];
		switch ( $this->action_data[ 'zone_slug' ] ) {

			case Secadmin::Slug():
				if ( $con->comps->sec_admin->isEnabledSecAdmin() ) {
					$hrefs[] = [
						'title' => __( 'Disable Security Admin', 'wp-simple-firewall' ),
						'href'  => $URLs->noncedPluginAction( SecurityAdminRemove::class, $URLs->adminHome(), [
							'quietly' => '1',
						] ),
					];
				}
				break;

			case Ips::Slug():
				$hrefs[] = [
					'title' => __( 'Reset CrowdSec Enrollment', 'wp-simple-firewall' ),
					'href'  => $URLs->noncedPluginAction( CrowdsecResetEnrollment::class, $URLs->adminHome() ),
				];
				break;

			case Scans::Slug():
				$hrefs[] = [
					'title' => __( 'View Scan Results', 'wp-simple-firewall' ),
					'href'  => $URLs->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ),
				];
				break;

			case Firewall::Slug():
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
				'options_form' => $con->action_router->render( SecurityZone::class, [
					'zone_slug' => $this->action_data[ 'zone_slug' ],
				] ),
			],
			'imgs'    => [
				'inner_page_title_icon' => $con->svgs->raw( 'sliders' ),
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