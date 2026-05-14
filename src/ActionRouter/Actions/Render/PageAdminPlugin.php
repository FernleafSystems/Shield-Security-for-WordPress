<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\NavMenuBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices\Handler;

class PageAdminPlugin extends BaseRender {

	use SecurityAdminNotRequired;

	public const SLUG = 'page_admin_plugin';
	public const TEMPLATE = '/wpadmin_pages/insights/index.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$route = ( new PageAdminPluginRouteResolver() )->resolve( $this->action_data, $con->isPluginAdmin() );
		$nav = $route[ 'nav' ];
		$subNav = $route[ 'subnav' ];
		$delegateAction = $route[ 'delegate_action' ];
		$delegateActionData = $route[ 'delegate_payload' ];

		return [
			'classes' => [
				'page_container' => 'page-'.$nav
			],
			'content' => [
				'rendered_page_body' => self::con()->action_router->render(
					$delegateAction::SLUG,
					$delegateActionData
				),
			],
			'flags'   => [
				'is_advanced' => false,
			],
			'imgs'    => [
				'logo_banner' => $con->labels->url_img_pagebanner,
				'logo_small'  => $con->labels->url_img_logo_small,
			],
			'strings' => \array_merge(
				$this->buildAdminShellStrings( $nav, $subNav ),
				[
					'top_page_warnings' => $this->buildTopPageWarnings(),
					'dismiss_notice'    => __( 'Dismiss', 'wp-simple-firewall' ),
				]
			),
			'vars'    => [
				'active_module_settings' => $subNav,
				'nav_sidebar'            => ( new NavMenuBuilder() )->build(),
			],
		];
	}

	private function buildAdminShellStrings( string $nav, string $subNav ) :array {
		$pluginName = self::con()->labels->Name;

		return [
			'admin_shell_title'            => sprintf(
				__( '%1$s - %2$s', 'wp-simple-firewall' ),
				$this->resolveRouteLabel( $nav, $subNav ),
				$pluginName
			),
			'admin_shell_main_label'       => sprintf( __( '%s admin content', 'wp-simple-firewall' ), $pluginName ),
			'admin_shell_sidebar_label'    => sprintf( __( '%s admin sidebar', 'wp-simple-firewall' ), $pluginName ),
			'admin_shell_navigation_label' => sprintf( __( '%s admin navigation', 'wp-simple-firewall' ), $pluginName ),
		];
	}

	private function resolveRouteLabel( string $nav, string $subNav ) :string {
		$navHierarchy = PluginNavs::GetNavHierarchy();
		$navLabel = \trim( (string)( $navHierarchy[ $nav ][ 'name' ] ?? '' ) );
		$routeLabel = \trim( (string)( $navHierarchy[ $nav ][ 'sub_navs' ][ $subNav ][ 'label' ] ?? '' ) );

		return empty( $routeLabel ) ? $navLabel : $routeLabel;
	}

	protected function buildTopPageWarnings() :array {
		return \array_filter(
			( new Handler() )->build(),
			function ( array $issue ) {
				return \in_array( 'shield_admin_top_page', $issue[ 'locations' ] );
			}
		);
	}
}
