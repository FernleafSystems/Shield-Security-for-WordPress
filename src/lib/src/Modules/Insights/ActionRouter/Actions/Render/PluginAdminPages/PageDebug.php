<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Debug\SimplePluginTests;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Debug\DebugRecentEvents;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Debug\Collate;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class PageDebug extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_debug';
	public const PRIMARY_MOD = 'plugin';
	public const TEMPLATE = '/wpadmin_pages/insights/plugin_admin/debug.twig';

	protected function getRenderData() :array {
		$con = $this->getCon();
		if ( !empty( $this->action_data[ Constants::NAV_SUB_ID ] ) ) {
			$debugExec = implode( "\n", $con->getModule_Insights()
											->getActionRouter()
											->action( SimplePluginTests::SLUG, [
												'test' => $this->action_data[ Constants::NAV_SUB_ID ]
											] )->action_response_data );
		}
		else {
			$debugExec = '';
		}

		$availableTests = [];
		if ( $con->this_req->is_security_admin && Services::Request()->query( 'show' ) ) {
			$availableTests = array_map(
				function ( $method ) {
					return sprintf(
						'<a href="%s" target="_blank">%s</a>',
						$this->getCon()
							 ->getModule_Insights()
							 ->getUrl_SubInsightsPage( 'debug', $method->getName() ),
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
			'flags'   => [
				'has_debug_exec' => !empty( $debugExec ),
				'display_tests'  => !empty( $availableTests ),
			],
			'hrefs'   => [
				'check_visitor_ip_source' => URL::Build( '', [ 'shield_check_ip_source' => '1' ] ),
			],
			'strings' => [
				'page_title' => sprintf( __( '%s Debug Page' ), $con->getHumanName() )
			],
			'vars'    => [
				'debug_data'      => empty( $debugExec ) ?
					( new Collate() )
						->setMod( $this->getMod() )
						->run() : '',
				'debug_exec'      => $debugExec,
				'available_tests' => $availableTests,
			],
			'content' => [
				'recent_events' => $con->getModule_Insights()
									   ->getActionRouter()
									   ->render( DebugRecentEvents::SLUG ),
			]
		];
	}
}