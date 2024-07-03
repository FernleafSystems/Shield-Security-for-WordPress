<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\CrowdsecResetEnrollment;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SecurityAdminRemove;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Enum\EnumModules;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Navigation\BuildBreadCrumbs;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\GetOptionsForZoneComponents;
use FernleafSystems\Wordpress\Services\Services;

class PageConfigForZoneComponents extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_config_for_zone_components';

	protected function getPageContextualHrefs() :array {
		return [];
		$URLs = self::con()->plugin_urls;
		$hrefs = [];
		switch ( $zone::Slug() ) {

			case EnumModules::ACTIVITY:
				$hrefs[] = [
					'text' => __( 'View Activity Log', 'wp-simple-firewall' ),
					'href' => $URLs->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS ),
				];
				$hrefs[] = [
					'text' => __( 'View Request Log', 'wp-simple-firewall' ),
					'href' => $URLs->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS ),
				];
				break;

			case EnumModules::SECURITY_ADMIN:
				if ( self::con()->comps->sec_admin->isEnabledSecAdmin() ) {
					$hrefs[] = [
						'text' => __( 'Disable Security Admin', 'wp-simple-firewall' ),
						'href' => $URLs->noncedPluginAction(
							SecurityAdminRemove::class,
							Services::WpGeneral()->getAdminUrl(),
							[
								'quietly' => '1',
							]
						),
					];
				}
				break;

			case EnumModules::SCANS:
				$hrefs[] = [
					'text' => __( 'Scan Results', 'wp-simple-firewall' ),
					'href' => $URLs->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ),
				];
				$hrefs[] = [
					'text' => __( 'Run Manual Scan', 'wp-simple-firewall' ),
					'href' => $URLs->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RUN ),
				];
				break;

			case EnumModules::IPS:
				$hrefs[] = [
					'text' => __( 'Reset CrowdSec Enrollment', 'wp-simple-firewall' ),
					'href' => $URLs->noncedPluginAction(
						CrowdsecResetEnrollment::class,
						$URLs->modCfg( EnumModules::IPS )
					),
				];
				break;

			default:
				break;
		}

		return $hrefs;
	}

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
		return $crumbs = parent::getBreadCrumbs();
		if ( empty( $crumbs ) ) {
			$crumbs = ( new BuildBreadCrumbs() )->for( PluginNavs::NAV_OPTIONS_CONFIG, $this->action_data[ 'mod_slug' ] );
		}
		return $crumbs;
	}

	protected function getRequiredDataKeys() :array {
		return [
			'zone_component_slugs',
		];
	}
}