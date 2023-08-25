<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\CrowdsecResetEnrollment;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsForm;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SecurityAdminRemove;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\{
	HackGuard,
	IPs,
	SecurityAdmin
};
use FernleafSystems\Wordpress\Services\Services;

class PageConfig extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_config';
	public const TEMPLATE = '/wpadmin_pages/plugin_admin/config.twig';

	protected function getPageContextualHrefs() :array {
		$URLs = self::con()->plugin_urls;
		$hrefs = [];
		switch ( $this->action_data[ 'mod_slug' ] ) {

			case SecurityAdmin\ModCon::SLUG:
				if ( self::con()->getModule_SecAdmin()->getSecurityAdminController()->isEnabledSecAdmin() ) {
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

			case HackGuard\ModCon::SLUG:
				$hrefs[] = [
					'text' => __( 'Scan Results', 'wp-simple-firewall' ),
					'href' => $URLs->adminTopNav( $URLs::NAV_SCANS_RESULTS ),
				];
				$hrefs[] = [
					'text' => __( 'Run Manual Scan', 'wp-simple-firewall' ),
					'href' => $URLs->adminTopNav( $URLs::NAV_SCANS_RUN ),
				];
				break;

			case IPs\ModCon::SLUG:
				$hrefs[] = [
					'text' => __( 'Reset CrowdSec Enrollment', 'wp-simple-firewall' ),
					'href' => $URLs->noncedPluginAction(
						CrowdsecResetEnrollment::class,
						$URLs->modCfg( self::con()->getModule_IPs() )
					),
				];
				break;

			default:
				break;
		}

		return $hrefs;
	}

	protected function getRenderData() :array {
		$con = $this->con();
		$mod = $con->modules[ $this->action_data[ 'mod_slug' ] ];
		return [
			'content' => [
				'options_form' => $con->action_router->render( OptionsForm::SLUG, $this->action_data ),
			],
			'imgs'    => [
				'inner_page_title_icon' => self::con()->svgs->raw( 'sliders' ),
			],
			'strings' => [
				'inner_page_title'    => sprintf( '%s > %s', __( 'Configuration', 'wp-simple-firewall' ), $mod->getDescriptors()[ 'title' ] ),
				'inner_page_subtitle' => $mod->getDescriptors()[ 'subtitle' ],
			],
		];
	}
}