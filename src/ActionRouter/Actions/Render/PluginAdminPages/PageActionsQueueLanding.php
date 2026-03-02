<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Meters\MeterCard;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	Component\Base as MeterComponent,
	Meter\MeterSummary
};

class PageActionsQueueLanding extends PageModeLandingBase {

	public const SLUG = 'plugin_admin_page_actions_queue_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/actions_queue_landing.twig';
	private ?array $needsAttentionPayload = null;

	protected function getLandingTitle() :string {
		return __( 'Actions Queue', 'wp-simple-firewall' );
	}

	protected function getLandingSubtitle() :string {
		return __( 'Review active issues and run the next action quickly.', 'wp-simple-firewall' );
	}

	protected function getLandingIcon() :string {
		return 'shield-shaded';
	}

	protected function getLandingContent() :array {
		$con = self::con();
		return [
			'action_meter'          => $con->action_router->render( MeterCard::class, [
				'meter_slug'    => MeterSummary::SLUG,
				'meter_channel' => MeterComponent::CHANNEL_ACTION,
				'is_hero'       => true,
			] ),
			'needs_attention_queue' => $this->getNeedsAttentionPayload()[ 'render_output' ],
		];
	}

	protected function getLandingFlags() :array {
		$renderData = $this->getNeedsAttentionRenderData();
		return [
			'queue_is_empty' => !$renderData[ 'flags' ][ 'has_items' ],
		];
	}

	protected function getLandingHrefs() :array {
		$con = self::con();
		return [
			'scan_results' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ),
			'scan_run'     => $con->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RUN ),
		];
	}

	protected function getLandingStrings() :array {
		return [
			'cta_title'           => __( 'Quick Actions', 'wp-simple-firewall' ),
			'cta_scan_results'    => __( 'Open Scan Results', 'wp-simple-firewall' ),
			'cta_scan_run'        => __( 'Run Manual Scan', 'wp-simple-firewall' ),
			'all_clear_title'     => $this->getNeedsAttentionString( 'all_clear_title' ),
			'all_clear_context'   => $this->getNeedsAttentionString( 'all_clear_subtitle' ),
			'all_clear_subtext'   => $this->getNeedsAttentionString( 'status_strip_subtext' ),
			'all_clear_icon_class' => $this->getNeedsAttentionString( 'all_clear_icon_class' ),
		];
	}

	/**
	 * @return array{
	 *   flags:array{has_items:bool},
	 *   strings:array{
	 *     all_clear_title:string,
	 *     all_clear_subtitle:string,
	 *     status_strip_subtext:string,
	 *     all_clear_icon_class:string
	 *   }
	 * }
	 */
	private function getNeedsAttentionRenderData() :array {
		return $this->getNeedsAttentionPayload()[ 'render_data' ];
	}

	private function getNeedsAttentionString( string $key ) :string {
		return $this->getNeedsAttentionRenderData()[ 'strings' ][ $key ];
	}

	/**
	 * @return array{
	 *   render_output:string,
	 *   render_data:array{
	 *     flags:array{has_items:bool},
	 *     strings:array{
	 *       all_clear_title:string,
	 *       all_clear_subtitle:string,
	 *       status_strip_subtext:string,
	 *       all_clear_icon_class:string
	 *     }
	 *   }
	 * }
	 */
	private function getNeedsAttentionPayload() :array {
		if ( $this->needsAttentionPayload === null ) {
			$this->needsAttentionPayload = self::con()
											->action_router
											->action( NeedsAttentionQueue::class, [
												'compact_all_clear' => true,
											] )
											->payload();
		}
		return $this->needsAttentionPayload;
	}
}
