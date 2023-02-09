<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsForm;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SecurityAdminRemove;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\{
	SecurityAdmin,
};
use FernleafSystems\Wordpress\Services\Services;

class PageConfig extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_config';
	public const TEMPLATE = '/wpadmin_pages/plugin_admin/config.twig';

	protected function getPageContextualHrefs() :array {
		$con = $this->getCon();
		$mod = $this->primary_mod;
		$WP = Services::WpGeneral();

		$hrefs = [];
		switch ( $mod->cfg->slug ) {

			case SecurityAdmin\ModCon::SLUG:
				/** @var SecurityAdmin\ModCon $mod */
				if ( $mod->getSecurityAdminController()->isEnabledSecAdmin() ) {
					$hrefs[] = [
						'text' => __( 'Disable Security Admin', 'wp-simple-firewall' ),
						'href' => $con->plugin_urls->noncedPluginAction(
							SecurityAdminRemove::class,
							$WP->getAdminUrl(),
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
		$mod = $this->primary_mod;
		return [
			'content' => [
				'options_form' => $this->getCon()->action_router->render( OptionsForm::SLUG, $this->action_data ),
			],
			'strings' => [
				'inner_page_title'    => sprintf( '%s > %s', __( 'Configuration', 'wp-simple-firewall' ), $mod->getModDescriptors()[ 'title' ] ),
				'inner_page_subtitle' => $mod->getModDescriptors()[ 'subtitle' ],
			],
		];
	}

	public function __get( string $key ) {
		$value = parent::__get( $key );

		switch ( $key ) {
			case 'primary_mod_slug':
				$value = $this->action_data[ 'primary_mod_slug' ];
				break;

			default:
				break;
		}

		return $value;
	}
}