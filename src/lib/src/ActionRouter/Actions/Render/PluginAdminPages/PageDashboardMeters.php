<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Meters\ProgressMeters;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SecurityOverviewViewAs;

class PageDashboardMeters extends BasePluginAdminPage {

	public const SLUG = 'plugin_admin_page_dashboard_meters';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/dashboard_meters.twig';

	protected function getPageContextualHrefs() :array {
		$hrefs = [];

		$viewAsState = $this->getViewAsState();
		if ( $viewAsState[ 'is_show_view_as' ] ) {
			$hrefs[] = $viewAsState[ 'view_as_href' ];
		}

		return $hrefs;
	}

	protected function getViewAsState() :array {
		$con = self::con();
		$URLs = $con->plugin_urls;

		$currentViewAs = $con->opts->optGet( 'sec_overview_prefs' )[ 'view_as' ] ?? 'business';

		if ( !$con->isPremiumActive() ) { // Free
			$showViewAs = true;
			$viewAsMsg = $currentViewAs === 'free' ?
				__( "Your security analysis doesn't consider ShieldPRO-only features - use the Cog menu to view your analysis as-if you had access to these extra security features.", 'wp-simple-firewall' )
				: __( "Your security analysis includes ShieldPRO-only features - use the Cog menu to view your analysis for your available security features.", 'wp-simple-firewall' );
			$viewAsHref = [
				'title' => $currentViewAs === 'free' ? __( 'View As ShieldPRO', 'wp-simple-firewall' ) : __( 'View As ShieldFREE', 'wp-simple-firewall' ),
				'href'  => $URLs->noncedPluginAction(
					SecurityOverviewViewAs::class,
					$URLs->adminHome(),
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
		$con = self::con();

		$viewAsState = $this->getViewAsState();

		return [
			'content' => [
				'progress_meters' => $con->action_router->render( ProgressMeters::class ),
			],
			'flags'   => [
				'is_show_view_as_message' => $viewAsState[ 'is_show_view_as' ],
			],
			'imgs'    => [
				'inner_page_title_icon' => $con->svgs->raw( 'speedometer' ),
			],
			'strings' => [
				'inner_page_title'    => __( 'Security Analysis', 'wp-simple-firewall' ),
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