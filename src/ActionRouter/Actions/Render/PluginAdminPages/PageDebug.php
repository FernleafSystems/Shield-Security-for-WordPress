<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\CrowdsecResetEnrollment;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Debug\SimplePluginTests;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Debug\DebugRecentEvents;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Debug\Collate;
use FernleafSystems\Wordpress\Services\Utilities\URL;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageDebug extends PageModeLandingBase {

	public const SLUG = 'admin_plugin_page_debug';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/debug.twig';

	protected function getPageContextualHrefs() :array {
		$URLs = self::con()->plugin_urls;
		return [
			[
				'title' => __( 'Force Check of Visitor IP Source', 'wp-simple-firewall' ),
				'href'  => URL::Build(
					$URLs->debugInfo(),
					[ 'shield_check_ip_source' => '1' ]
				),
			],
			[
				'title'     => __( 'Purge Provider IPs', 'wp-simple-firewall' ),
				'href'      => '',
				'is_action' => true,
				'classes'   => [ 'tool_purge_provider_ips' ],
			],
			[
				'title' => __( 'Reset CrowdSec Enrollment', 'wp-simple-firewall' ),
				'href'  => $URLs->noncedPluginAction( CrowdsecResetEnrollment::class, $URLs->debugInfo() ),
			],
			[
				'title'     => __( 'Print', 'wp-simple-firewall' ),
				'href'      => '',
				'is_action' => true,
				'classes'   => [ 'shield_div_print' ],
				'data'      => [ 'selector' => '#PageMainBody_Inner-Apto' ],
			],
		];
	}

	protected function getRenderData() :array {
		$con = self::con();

		$availableTests = [];
		if ( $con->this_req->is_security_admin && ( $this->action_data[ 'show' ] ?? false ) ) {
			$availableTests = \array_map(
				fn( $method ) => sprintf(
					'<a href="%s" target="_blank">%s</a>',
					$con->plugin_urls->noncedPluginAction( SimplePluginTests::class, null, [
						'test' => $method->getName()
					] ),
					\str_replace( 'dbg_', '', $method->getName() )
				),
				\array_filter(
					( new \ReflectionClass( SimplePluginTests::class ) )->getMethods(),
					fn( $method ) => \strpos( $method->getName(), 'dbg_' ) === 0
				)
			);
		}

		return \array_replace_recursive( parent::getRenderData(), [
			'content' => [
				'recent_events' => $con->action_router->render( DebugRecentEvents::class ),
			],
			'flags'   => [
				'display_tests' => !empty( $availableTests ),
			],
			'strings' => [
				'debug_tests_heading' => __( 'Debug Tests', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'debug_data'      => ( new Collate() )->run(),
				'available_tests' => $availableTests,
			],
		] );
	}

	protected function getLandingTitle() :string {
		return sprintf( __( '%s Debug Information', 'wp-simple-firewall' ), self::con()->labels->Name );
	}

	protected function getLandingSubtitle() :string {
		return __( 'Assess the state of the plugin and view various configuration information for your site.', 'wp-simple-firewall' );
	}

	protected function getLandingIcon() :string {
		return 'patch-question';
	}

	protected function getLandingMode() :string {
		return PluginNavs::MODE_CONFIGURE;
	}

	protected function hasOperatorModeParent() :bool {
		return true;
	}
}
