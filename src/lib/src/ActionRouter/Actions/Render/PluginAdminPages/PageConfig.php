<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsForm;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SecurityAdminRemove;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;
use FernleafSystems\Wordpress\Services\Services;

class PageConfig extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_config';
	public const TEMPLATE = '/wpadmin_pages/plugin_admin/config.twig';

	protected function getPageContextualHrefs() :array {
		$con = $this->getCon();
		$hrefs = [];
		switch ( $this->action_data[ 'mod_slug' ] ) {

			case SecurityAdmin\ModCon::SLUG:
				if ( $con->getModule_SecAdmin()->getSecurityAdminController()->isEnabledSecAdmin() ) {
					$hrefs[] = [
						'text' => __( 'Disable Security Admin', 'wp-simple-firewall' ),
						'href' => $con->plugin_urls->noncedPluginAction(
							SecurityAdminRemove::class,
							Services::WpGeneral()->getAdminUrl(),
							[
								'quietly' => '1',
							]
						),
					];
				}
				break;

			default:
				break;
		}

		return $hrefs;
	}

	protected function getRenderData() :array {
		$con = $this->getCon();
		$mod = $con->modules[ $this->action_data[ 'mod_slug' ] ];
		return [
			'content' => [
				'options_form' => $con->action_router->render( OptionsForm::SLUG, $this->action_data ),
			],
			'strings' => [
				'inner_page_title'    => sprintf( '%s > %s', __( 'Configuration', 'wp-simple-firewall' ), $mod->getDescriptors()[ 'title' ] ),
				'inner_page_subtitle' => $mod->getDescriptors()[ 'subtitle' ],
			],
		];
	}
}