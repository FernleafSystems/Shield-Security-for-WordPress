<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Services\Services;

/**
 * White Label as a page of its own: its settings laid out as a Configure panel, beside a preview of the
 * places they show that follows as you type.
 */
class PageToolWhitelabel extends PageModeLandingBase {

	public const SLUG = 'admin_plugin_page_tools_whitelabel';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/tool_whitelabel.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$opts = $con->opts;
		$text = fn( string $key ) :string => (string)$opts->optGet( $key );

		return \array_replace_recursive( parent::getRenderData(), [
			'strings' => [
				'lede'          => __( "Show your own name, links and logos in place of Shield's, across WordPress and the two-factor login page. The preview follows as you type.", 'wp-simple-firewall' ),
				'preview_label' => __( 'Preview', 'wp-simple-firewall' ),
				'wp_menu'       => __( 'WordPress menu', 'wp-simple-firewall' ),
				'plugins_list'  => __( 'Plugins list', 'wp-simple-firewall' ),
				'login_page'    => __( 'Two-factor login page', 'wp-simple-firewall' ),
				'by'            => __( 'By', 'wp-simple-firewall' ),
				'menu_plugins'  => __( 'Plugins', 'wp-simple-firewall' ),
				'menu_users'    => __( 'Users', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'preview'   => [
					'name'        => $text( 'wl_pluginnamemain' ),
					'menu'        => $text( 'wl_namemenu' ),
					'company'     => $text( 'wl_companyname' ),
					'description' => $text( 'wl_description' ),
					'home'        => $con->comps->whitelabel->homeUrl(),
					'icon'        => $this->imageUrl( 'wl_menuiconurl' ),
					'banner'      => $this->imageUrl( 'wl_login2fa_logourl' ),
				],
				'form_html' => $con->action_router->render( OptionsFormFor::class, [
					'options'         => ConfigureGoalPanels::ownPageOptions()[ 'whitelabel' ],
					'configure_panel' => 'own:whitelabel',
					'form_context'    => 'tool_page',
				] ),
			],
		] );
	}

	/**
	 * A logo setting holds a full address, a site-relative path, or the name of one of Shield's own images.
	 */
	private function imageUrl( string $key ) :string {
		$value = (string)self::con()->opts->optGet( $key );
		if ( $value === '' || Services::Data()->isValidWebUrl( $value ) || \strpos( $value, '/' ) === 0 ) {
			return $value;
		}
		return (string)self::con()->urls->forImage( $value );
	}

	protected function getLandingTitle() :string {
		return __( 'White Label', 'wp-simple-firewall' );
	}

	protected function getLandingSubtitle() :string {
		return __( "Show your own name, links and logos in place of Shield's.", 'wp-simple-firewall' );
	}

	protected function getLandingIcon() :string {
		return 'palette';
	}

	protected function getLandingMode() :string {
		return PluginNavs::MODE_CONFIGURE;
	}

	protected function hasOperatorModeParent() :bool {
		return true;
	}
}
