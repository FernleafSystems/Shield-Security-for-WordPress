<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\Rename\RenameLogin;

/**
 * Hide Login as a page of its own: where the login page is now, a plain warning that this isn't a security
 * setting, then the new address and where the old one sends people, laid out as a Configure panel.
 */
class PageToolLoginHide extends PageModeLandingBase {

	public const SLUG = 'admin_plugin_page_tools_loginhide';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/tool_loginhide.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$rename = new RenameLogin();
		$path = $rename->customPath();
		$home = \trailingslashit( \home_url() );
		$display = \preg_replace( '#^https?://#', '', $home );

		if ( $path === '' ) {
			$state = 'off';
		}
		else {
			$state = $rename->isEnabled() ? 'on' : 'blocked';
		}

		return \array_replace_recursive( parent::getRenderData(), [
			'strings' => [
				'lede'          => __( 'Move the WordPress login page to an address only you know, so simple bots stop finding it.', 'wp-simple-firewall' ),
				'off_title'     => __( 'Your login page is at the usual address', 'wp-simple-firewall' ),
				/* translators: %s: the login page's address */
				'off_desc'      => __( '%s, where every bot looks first.', 'wp-simple-firewall' ),
				'off_pill'      => __( 'Not hidden', 'wp-simple-firewall' ),
				'on_title'      => __( 'Your login page is hidden', 'wp-simple-firewall' ),
				/* translators: %s: the login page's new address */
				'on_desc'       => __( "It's now at %s. Keep that address somewhere safe.", 'wp-simple-firewall' ),
				'on_pill'       => __( 'Hidden', 'wp-simple-firewall' ),
				'blocked_title' => __( "A new address is set, but it isn't in use", 'wp-simple-firewall' ),
				'blocked_desc'  => __( 'Another plugin, or how this site is set up, stops Shield moving the login page.', 'wp-simple-firewall' ),
				'blocked_pill'  => __( 'Not in use', 'wp-simple-firewall' ),
				'warning_title' => __( 'This is not a security setting', 'wp-simple-firewall' ),
				'warning_desc'  => __( "Hiding the login page only keeps simple bots and people away; it doesn't make your site more secure. The address can leak through other plugins and themes, so treat it as known and keep your real protections, such as two-factor authentication, on.", 'wp-simple-firewall' ),
				'warning_more'  => __( 'Why hiding the login page is cosmetic', 'wp-simple-firewall' ),
			],
			'flags'   => [
				'state' => $state,
			],
			'hrefs'   => [
				'warning_more' => 'https://clk.shldscrty.com/5r',
			],
			'vars'    => [
				'usual_address' => $display.'wp-login.php',
				'new_address'   => $display.$path,
				'form_html'     => $con->action_router->render( OptionsFormFor::class, [
					'options'         => ConfigureGoalPanels::ownPageOptions()[ 'login_hide' ],
					'configure_panel' => 'own:login_hide',
					'form_context'    => 'tool_page',
				] ),
			],
		] );
	}

	protected function getLandingTitle() :string {
		return __( 'Hide Login', 'wp-simple-firewall' );
	}

	protected function getLandingSubtitle() :string {
		return __( 'Move the WordPress login page to an address only you know.', 'wp-simple-firewall' );
	}

	protected function getLandingIcon() :string {
		return 'person-lock';
	}

	protected function getLandingMode() :string {
		return PluginNavs::MODE_CONFIGURE;
	}

	protected function hasOperatorModeParent() :bool {
		return true;
	}
}
