<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Debug\SimplePluginTests;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Debug\DebugRecentEvents;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Debug\Collate;
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

		return [
			'flags'   => [
				'has_debug_exec' => !empty( $debugExec ),
			],
			'hrefs'   => [
				'check_visitor_ip_source' => URL::Build( '', [ 'shield_check_ip_source' => '1' ] ),
			],
			'strings' => [
				'page_title' => sprintf( __( '%s Debug Page' ), $con->getHumanName() )
			],
			'vars'    => [
				'debug_data' => ( new Collate() )
					->setMod( $this->getMod() )
					->run(),
				'debug_exec' => $debugExec,
			],
			'content' => [
				'recent_events' => $con->getModule_Insights()
									   ->getActionRouter()
									   ->render( DebugRecentEvents::SLUG ),
			]
		];
	}
}