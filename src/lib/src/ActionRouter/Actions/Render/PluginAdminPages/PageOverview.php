<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Meters\ProgressMeters;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SecurityOverviewViewAs;

class PageOverview extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_overview';
	public const TEMPLATE = '/wpadmin_pages/plugin_admin/overview.twig';

	protected function getPageContextualHrefs() :array {
		$hrefs = [];

		$viewAsState = $this->getViewAsState();
		if ( $viewAsState[ 'is_show_view_as' ] ) {
			$hrefs[] = $viewAsState[ 'view_as_href' ];
		}

		return $hrefs;
	}

	protected function getViewAsState() :array {
		$con = $this->con();
		$URLs = $con->plugin_urls;

		$currentViewAs = $this->con()->getModule_Plugin()->getOptions()->getOpt( 'sec_overview_prefs' )[ 'view_as' ]
						 ?? 'business';

		if ( !$con->isPremiumActive() ) { // Free
			$showViewAs = true;
			$viewAsMsg = $currentViewAs === 'free' ?
				__( "Your security analysis doesn't consider ShieldPRO-only features - use the Cog menu to view your analysis as-if you had access to these extra security features.", 'wp-simple-firewall' )
				: __( "Your security analysis includes ShieldPRO-only features - use the Cog menu to view your analysis for your available security features.", 'wp-simple-firewall' );
			$viewAsHref = [
				'text' => $currentViewAs === 'free' ? __( 'View As ShieldPRO', 'wp-simple-firewall' ) : __( 'View As ShieldFREE', 'wp-simple-firewall' ),
				'href' => $URLs->noncedPluginAction(
					SecurityOverviewViewAs::class,
					$URLs->adminTopNav( $URLs::NAV_OVERVIEW ),
					[
						'view_as' => $currentViewAs === 'free' ? 'business' : 'free',
					]
				),
			];
		}
		else { // Business
			$showViewAs = false;
			$viewAsMsg = '';
			$viewAsHref = [];
		}

		return [
			'is_show_view_as' => $showViewAs,
			'view_as_msg'     => $viewAsMsg,
			'view_as_href'    => $viewAsHref,
		];
	}

	protected function getRenderData() :array {
		$con = $this->con();

		$viewAsState = $this->getViewAsState();

		return [
			'content' => [
				'progress_meters' => $con->action_router->render( ProgressMeters::SLUG ),
			],
			'flags'   => [
				'is_show_view_as_message' => $viewAsState[ 'is_show_view_as' ],
			],
			'imgs'    => [
				'inner_page_title_icon' => $con->svgs->raw( 'speedometer' ),
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
				'view_as_message'    => $viewAsState[ 'view_as_msg' ],
			],
		];
	}
}