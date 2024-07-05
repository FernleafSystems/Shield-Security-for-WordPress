<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\CrowdsecResetEnrollment;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsForm;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SecurityAdminRemove;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\StringsModules;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Enum\EnumModules;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Navigation\BuildBreadCrumbs;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone\Ips;
use FernleafSystems\Wordpress\Services\Services;

class PageConfig extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_config';

	protected function getPageContextualHrefs() :array {
		$URLs = self::con()->plugin_urls;
		$hrefs = [];
		switch ( $this->action_data[ 'mod_slug' ] ) {

			case EnumModules::ACTIVITY:
				$hrefs[] = [
					'title' => __( 'View Activity Log', 'wp-simple-firewall' ),
					'href' => $URLs->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS ),
				];
				$hrefs[] = [
					'title' => __( 'View Request Log', 'wp-simple-firewall' ),
					'href' => $URLs->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS ),
				];
				break;

			case EnumModules::SECURITY_ADMIN:
				if ( self::con()->comps->sec_admin->isEnabledSecAdmin() ) {
					$hrefs[] = [
						'title' => __( 'Disable Security Admin', 'wp-simple-firewall' ),
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
					'title' => __( 'Scan Results', 'wp-simple-firewall' ),
					'href' => $URLs->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ),
				];
				$hrefs[] = [
					'title' => __( 'Run Manual Scan', 'wp-simple-firewall' ),
					'href' => $URLs->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RUN ),
				];
				break;

			case EnumModules::IPS:
				$hrefs[] = [
					'title' => __( 'Reset CrowdSec Enrollment', 'wp-simple-firewall' ),
					'href' => $URLs->noncedPluginAction( CrowdsecResetEnrollment::class, $URLs->zone( Ips::Slug() ) ),
				];
				break;

			default:
				break;
		}

		return $hrefs;
	}

	protected function getRenderData() :array {
		$modStrings = ( new StringsModules() )->getFor( $this->action_data[ 'mod_slug' ] );
		return [
			'content' => [
				'options_form' => self::con()->action_router->render( OptionsForm::class, $this->action_data ),
			],
			'imgs'    => [
				'inner_page_title_icon' => self::con()->svgs->raw( 'sliders' ),
			],
			'strings' => [
				'inner_page_title'    => $modStrings[ 'name' ],
				'inner_page_subtitle' => $modStrings[ 'subtitle' ],
			],
		];
	}

	/**
	 * Must manually build breadcrumbs for dynamic loaded config.
	 */
	protected function getBreadCrumbs() :array {
		$crumbs = parent::getBreadCrumbs();
		if ( empty( $crumbs ) ) {
			$crumbs = ( new BuildBreadCrumbs() )->for( PluginNavs::NAV_OPTIONS_CONFIG, $this->action_data[ 'mod_slug' ] );
		}
		return $crumbs;
	}
}