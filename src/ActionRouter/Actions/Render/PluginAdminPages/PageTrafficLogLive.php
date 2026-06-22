<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\Render\Components\Traffic\TrafficLiveLogs,
};

class PageTrafficLogLive extends PageTrafficLogBase {

	public const SLUG = 'page_admin_plugin_traffic_log_live';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/traffic_logs_live.twig';

	protected function getPageContextualHrefs() :array {
		$hrefs = parent::getPageContextualHrefs();
		\array_unshift( $hrefs, [
			'title' => __( 'Switch To Normal Logs', 'wp-simple-firewall' ),
			'href'  => self::con()->plugin_urls->trafficLog(),
		] );
		return $hrefs;
	}

	protected function getRenderData() :array {
		$renderActionData = [];
		if ( \array_key_exists( 'limit', $this->action_data ) ) {
			$renderActionData[ 'limit' ] = $this->action_data[ 'limit' ];
		}

		$timeRemaining = self::con()->comps->opts_lookup->getTrafficLiveLogTimeRemaining();

		return [
			'ajax'    => [
				'load_live_logs' => ActionData::BuildJson( TrafficLiveLogs::class, true, $renderActionData ),
			],
			'imgs'    => [
				'inner_page_title_icon' => self::con()->svgs->iconClass( 'stoplights' ),
			],
			'strings' => [
				'inner_page_title'                 => __( 'View Live HTTP Logs', 'wp-simple-firewall' ),
				'inner_page_subtitle'              => __( 'View request logs as they reach your site.', 'wp-simple-firewall' ),
				'live_log_control_label'           => __( 'Live Traffic Logging', 'wp-simple-firewall' ),
				'live_log_control_title'           => __( 'Live Traffic Capture', 'wp-simple-firewall' ),
				'live_log_control_enabled_summary'  => __( 'All requests are being captured for the live view.', 'wp-simple-firewall' ),
				'live_log_control_disabled_summary' => __( 'Only requests that match normal logging rules are shown.', 'wp-simple-firewall' ),
				'live_log_unavailable'             => __( 'Live traffic capture is not available for this site.', 'wp-simple-firewall' ),
				'not_enabled'                      => __( 'Live traffic capture is off, so quiet requests without parameters may not appear here.', 'wp-simple-firewall' ),
				'live_view_status'                 => __( 'Viewing the latest request entries as they arrive.', 'wp-simple-firewall' ),
				'waiting_live_logs'                => __( 'Waiting for live updates...', 'wp-simple-firewall' ),
				'update_failed'                    => __( 'Live log update failed.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'live_log_control' => [
					'id'             => 'TrafficLiveLogToggle',
					'is_available'   => self::con()->caps->canTrafficLiveLog(),
					'is_enabled'     => $timeRemaining > 0,
					'time_remaining' => $timeRemaining,
				],
			],
		];
	}
}
