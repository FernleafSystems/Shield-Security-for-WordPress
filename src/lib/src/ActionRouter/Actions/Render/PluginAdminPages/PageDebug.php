<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Debug\SimplePluginTests;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Debug\DebugRecentEvents;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Debug\Collate;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class PageDebug extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_debug';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/debug.twig';

	protected function getPageContextualHrefs() :array {
		return [
			[
				'title' => __( 'Force Check of Visitor IP Source', 'wp-simple-firewall' ),
				'href'  => URL::Build(
					self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_TOOLS, PluginNavs::SUBNAV_TOOLS_DEBUG ),
					[ 'shield_check_ip_source' => '1' ]
				),
			],
			[
				'title'   => __( 'Purge Provider IPs', 'wp-simple-firewall' ),
				'href'    => '#',
				'classes' => [ 'tool_purge_provider_ips' ],
			],
			[
				'title'   => __( 'Print', 'wp-simple-firewall' ),
				'href'    => "javascript:{}",
				'classes' => [ 'shield_div_print' ],
				'data'    => [ 'selector' => '#PageMainBody_Inner-Apto' ],
			],
		];
	}

	protected function getRenderData() :array {
		$con = self::con();

		$availableTests = [];
		if ( $con->this_req->is_security_admin && ( $this->action_data[ 'show' ] ?? false ) ) {
			$availableTests = \array_map(
				function ( $method ) {
					return sprintf(
						'<a href="%s" target="_blank">%s</a>',
						self::con()->plugin_urls->noncedPluginAction( SimplePluginTests::class, null, [
							'test' => $method->getName()
						] ),
						\str_replace( 'dbg_', '', $method->getName() )
					);
				},
				\array_filter(
					( new \ReflectionClass( SimplePluginTests::class ) )->getMethods(),
					function ( $method ) {
						return \strpos( $method->getName(), 'dbg_' ) === 0;
					}
				)
			);
		}

		return [
			'content' => [
				'recent_events' => $con->action_router->render( DebugRecentEvents::class ),
			],
			'flags'   => [
				'display_tests' => !empty( $availableTests ),
			],
			'imgs'    => [
				'inner_page_title_icon' => self::con()->svgs->raw( 'patch-question' ),
			],
			'strings' => [
				'inner_page_title'    => sprintf( __( '%s Debug Information' ), $con->getHumanName() ),
				'inner_page_subtitle' => __( 'Assess the state of the plugin and view various configuration information for your site.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'debug_data'      => ( new Collate() )->run(),
				'available_tests' => $availableTests,
			],
		];
	}
}