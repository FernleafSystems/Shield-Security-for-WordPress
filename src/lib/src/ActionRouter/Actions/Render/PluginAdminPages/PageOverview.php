<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Meters\ProgressMeters;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SecurityOverviewViewAs;

class PageOverview extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_overview';
	public const TEMPLATE = '/wpadmin_pages/plugin_admin/overview.twig';

	protected function getPageContextualHrefs() :array {
		$con = $this->con();
		$URLs = $con->plugin_urls;
		$hrefs = [];
		if ( !$con->isPremiumActive() ) {
			$hrefs[] = [
				'text' => $this->isViewAsPro() ? __( 'View Analysis As ShieldFREE', 'wp-simple-firewall' )
					: __( 'View Analysis As ShieldPRO', 'wp-simple-firewall' ),
				'href' => $URLs->noncedPluginAction(
					SecurityOverviewViewAs::class,
					$URLs->adminTopNav( $URLs::NAV_OVERVIEW ),
					[
						'view_as' => $this->isViewAsPro() ? 'free' : 'pro',
					]
				),
			];
		}
		return $hrefs;
	}

	protected function getRenderData() :array {
		$con = $this->con();
		return [
			'content' => [
				'progress_meters' => $con->action_router->render( ProgressMeters::SLUG ),
			],
			'flags'   => [
				'is_show_view_as_message' => !$con->isPremiumActive(),
			],
			'strings' => [
				'inner_page_title'    => __( 'Security Overview', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'View your entire WordPress site security at a glance and discover where you can improve.', 'wp-simple-firewall' ),

				'click_clear_filter' => __( 'Click To Filter By Security Area or Status', 'wp-simple-firewall' ),
				'clear_filter'       => __( 'Clear Filter', 'wp-simple-firewall' ),
				'go_to_options'      => sprintf(
					__( 'Go To %s', 'wp-simple-firewall' ),
					__( 'Options' )
				),
				'view_as_message'    => $this->isViewAsPro() ? __( "Your security analysis includes ShieldPRO-only features - use the Cog menu to view your analysis for your available security features.", 'wp-simple-firewall' ) :
					__( "Your security analysis doesn't consider ShieldPRO-only features - use the Cog menu to view your analysis as-if you had access to these extra security features.", 'wp-simple-firewall' ),
			],
		];
	}

	private function isViewAsPro() :bool {
		return ( $this->con()->getModule_Plugin()->getOptions()->getOpt( 'sec_overview_prefs' )[ 'view_as' ] ?? '' )
			   === 'pro';
	}
}