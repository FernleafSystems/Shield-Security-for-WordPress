<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Debug\SimplePluginTests;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Debug\DebugRecentEvents;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Debug\Collate;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class PageDebug extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_debug';
	public const TEMPLATE = '/wpadmin_pages/plugin_admin/debug.twig';

	protected function getPageContextualHrefs() :array {
		return [
			[
				'text' => __( 'Force Check of Visitor IP Source', 'wp-simple-firewall' ),
				'href' => URL::Build( $this->getCon()->plugin_urls->adminTopNav( PluginURLs::NAV_DEBUG ), [ 'shield_check_ip_source' => '1' ] ),
			],
		];
	}

	protected function getRenderData() :array {
		$con = $this->getCon();
		$urls = $con->plugin_urls;

		$availableTests = [];
		if ( $con->this_req->is_security_admin && Services::Request()->query( 'show' ) ) {
			$availableTests = array_map(
				function ( $method ) use ( $urls ) {
					return sprintf(
						'<a href="%s" target="_blank">%s</a>',
						$urls->noncedPluginAction( SimplePluginTests::class, null, [
							'test' => $method->getName()
						] ),
						str_replace( 'dbg_', '', $method->getName() )
					);
				},
				array_filter(
					( new \ReflectionClass( SimplePluginTests::class ) )->getMethods(),
					function ( $method ) {
						return strpos( $method->getName(), 'dbg_' ) === 0;
					}
				)
			);
		}

		return [
			'content' => [
				'recent_events' => $con->action_router->render( DebugRecentEvents::SLUG ),
			],
			'flags'   => [
				'display_tests' => !empty( $availableTests ),
			],
			'strings' => [
				'inner_page_title'    => sprintf( __( '%s Debug Information' ), $con->getHumanName() ),
				'inner_page_subtitle' => __( 'Assess the state of the plugin and view various configuration information for your site.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'debug_data'      => ( new Collate() )
					->setMod( $this->getMod() )
					->run(),
				'available_tests' => $availableTests,
			],
		];
	}
}