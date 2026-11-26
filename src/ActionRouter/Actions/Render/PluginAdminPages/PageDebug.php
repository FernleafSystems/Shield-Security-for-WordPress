<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\CrowdsecResetEnrollment;
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

		return \array_replace_recursive( parent::getRenderData(), [
			'content' => [
				'recent_events' => $con->action_router->render( DebugRecentEvents::class ),
			],
			'vars'    => [
				'debug_data' => $this->filterDebugData( ( new Collate() )->run() ),
			],
		] );
	}

	/**
	 * The values are printed as HTML for their code, line breaks and table listings. Server, database and plugin
	 * details in them, and their translations, can't add any other markup or any attributes.
	 */
	private function filterDebugData( array $data ) :array {
		$allowed = [
			'address' => [],
			'br'      => [],
			'code'    => [],
			'pre'     => [],
		];
		\array_walk_recursive( $data, static function ( &$value ) use ( $allowed ) {
			$value = wp_kses( (string)$value, $allowed );
		} );
		return $data;
	}

	protected function getLandingTitle() :string {
		/* translators: %s: plugin name */
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
