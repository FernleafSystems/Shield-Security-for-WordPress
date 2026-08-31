<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-import-type RawDrillLayer from PageDrillDownLandingBase
 * @phpstan-import-type QueueSummary from ActionsQueueLandingViewBuilder
 * @phpstan-import-type ZoneGroup from ActionsQueueLandingViewBuilder
 */
class PageActionsQueueLanding extends PageDrillDownLandingBase {

	use BuildsActionsQueueLandingData;

	private ?ActionsQueueDrillDownPresentationBuilder $drillDownPresentation = null;

	public const SLUG = 'plugin_admin_page_actions_queue_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/actions_queue_landing.twig';

	protected function getLandingTitle() :string {
		return __( 'Actions Queue', 'wp-simple-firewall' );
	}

	protected function getLandingSubtitle() :string {
		return __( 'Review active issues and run the next action quickly.', 'wp-simple-firewall' );
	}

	protected function getLandingIcon() :string {
		return 'shield-shaded';
	}

	protected function getLandingMode() :string {
		return PluginNavs::MODE_ACTIONS;
	}

	protected function getLandingFlags() :array {
		return [
			'queue_is_empty'        => !$this->getQueueSummary()[ 'has_items' ],
			'has_drilldown_content' => $this->hasDrilldownContent(),
		];
	}

	protected function getLandingHrefs() :array {
		$con = self::con();
		return [
			'scan_results' => $con->plugin_urls->actionsQueueScans(),
			'wp_updates'   => Services::WpGeneral()->getAdminUrl_Updates(),
		];
	}

	protected function getLandingStrings() :array {
		$zones = $this->getZonesIndexed();

		return [
			'zone_scans'             => $zones[ 'scans' ][ 'label' ],
			'zone_maintenance'       => $zones[ 'maintenance' ][ 'label' ],
			'pane_loading'           => __( 'Loading scan details...', 'wp-simple-firewall' ),
			'groups_loading'         => __( 'Loading issue groups...', 'wp-simple-firewall' ),
			'detail_loading'         => __( 'Loading results...', 'wp-simple-firewall' ),
			'pane_load_error'        => __( 'Unable to load these scan details. Please try again.', 'wp-simple-firewall' ),
			'layer_load_error'       => __( 'Unable to load this step. Please try again.', 'wp-simple-firewall' ),
			'layer_retry'            => __( 'Retry', 'wp-simple-firewall' ),
		];
	}

	protected function getOperatorRootStep() :array {
		return \array_replace(
			parent::getOperatorRootStep(),
			$this->buildActionsQueueOperatorRootStep()
		);
	}

	protected function getLandingVars() :array {
		$viewData = $this->getLandingViewData();
		$groupsRenderAction = ActionData::BuildAjaxRender( ActionsQueueDrillDownGroups::class );

		return \array_merge( parent::getLandingVars(), [
			'zone_tiles'         => $viewData[ 'zone_tiles' ],
			'actions_queue_ajax' => [
				'groups_render_action_json' => OperatorChromeContract::encodeJson( $groupsRenderAction ),
			],
			'actions_queue_pro_upsell' => [
				'template_id' => 'actions-queue-pro-upsell-template',
				'title_id'    => 'actions-queue-pro-upsell-title',
				'logo_url'    => self::con()->urls->forImage( 'plugin_logo_prem_dark.svg' ),
				'left_lines'  => [
					[
						'text'     => __( 'Free protection is real.', 'wp-simple-firewall' ),
						'emphasis' => '',
					],
					[
						'text'     => __( 'Pro protection is ', 'wp-simple-firewall' ),
						'emphasis' => __( 'complete.', 'wp-simple-firewall' ),
					],
				],
				'heading'     => __( 'See what Shield Pro adds', 'wp-simple-firewall' ),
				'labels'      => [
					'close'            => __( 'Close', 'wp-simple-firewall' ),
					'protection'       => __( 'Protection', 'wp-simple-firewall' ),
					'free'             => __( 'Free', 'wp-simple-firewall' ),
					'pro'              => __( 'Pro', 'wp-simple-firewall' ),
					'included'         => __( 'Included', 'wp-simple-firewall' ),
					'not_included'     => __( 'Not included', 'wp-simple-firewall' ),
					'view_pro_plans'   => __( 'View Pro plans', 'wp-simple-firewall' ),
					'compare_features' => __( 'Compare every feature', 'wp-simple-firewall' ),
				],
				'rows'        => [
					[ 'label' => __( 'Core hardening', 'wp-simple-firewall' ), 'free' => true, 'pro' => true ],
					[ 'label' => __( 'Auto bad bot blocking', 'wp-simple-firewall' ), 'free' => true, 'pro' => true ],
					[ 'label' => __( 'WP core file scanning', 'wp-simple-firewall' ), 'free' => true, 'pro' => true ],
					[ 'label' => __( 'Malware scanning with MAL{ai}', 'wp-simple-firewall' ), 'free' => false, 'pro' => true ],
					[ 'label' => __( 'Vulnerability detection', 'wp-simple-firewall' ), 'free' => false, 'pro' => true ],
					[ 'label' => __( 'Plugins and themes file scanning', 'wp-simple-firewall' ), 'free' => false, 'pro' => true ],
					[ 'label' => __( 'Critical File Locker (wp-config.php)', 'wp-simple-firewall' ), 'free' => false, 'pro' => true ],
					[ 'label' => __( 'ShieldBACKUP Disaster Recovery', 'wp-simple-firewall' ), 'free' => false, 'pro' => true ],
				],
				'hrefs'       => [
					'go_pro'           => self::GO_PRO_URL,
					'compare_features' => self::COMPARE_FEATURES_URL,
				],
			],
		] );
	}

	/**
	 * @return list<RawDrillLayer>
	 */
	protected function getLayers() :array {
		$summary = $this->getQueueSummary();
		$presentation = $this->drillDownPresentation();

		return [
			[
				'key'    => 'buckets',
				'body'   => $this->renderBucketsLayer(),
				'header' => [
					'compact_back_label' => $presentation->buildBackLabel( __( 'Actions Queue', 'wp-simple-firewall' ) ),
					'breadcrumb_label'   => __( 'Queue areas', 'wp-simple-firewall' ),
					'title'              => __( 'Queue areas', 'wp-simple-firewall' ),
					'summary'            => __( 'Choose the queue area that needs attention first.', 'wp-simple-firewall' ),
					'next_step'          => __( 'Choose Fix now or Review next to continue.', 'wp-simple-firewall' ),
					'icon_class'         => 'bi bi-inboxes',
					'badge_status'       => $summary[ 'severity' ],
					'color_key'          => $summary[ 'severity' ],
				],
			],
			[
				'key'    => 'groups',
				'body'   => '',
				'header' => [
					'compact_back_label' => $presentation->buildBackLabel( __( 'Issue Groups', 'wp-simple-firewall' ) ),
					'active_back_label'  => $presentation->buildBackLabel( __( 'Actions Queue', 'wp-simple-firewall' ) ),
					'breadcrumb_label'   => __( 'Issue groups', 'wp-simple-firewall' ),
					'title'              => __( 'Issue groups', 'wp-simple-firewall' ),
					'summary'            => __( 'Choose Fix now or Review next to start.', 'wp-simple-firewall' ),
					'next_step'          => __( 'Choose one issue group to review matching results.', 'wp-simple-firewall' ),
					'icon_class'         => 'bi bi-list-ul',
					'badge'              => __( 'Select', 'wp-simple-firewall' ),
					'badge_status'       => 'neutral',
					'color_key'          => 'neutral',
				],
			],
			[
				'key'    => 'detail',
				'body'   => '',
				'header' => [
					'compact_back_label' => $presentation->buildBackLabel( __( 'Results', 'wp-simple-firewall' ) ),
					'active_back_label'  => $presentation->buildBackLabel( __( 'Issue Groups', 'wp-simple-firewall' ) ),
					'breadcrumb_label'   => __( 'Results', 'wp-simple-firewall' ),
					'title'              => __( 'Results', 'wp-simple-firewall' ),
					'summary'            => __( 'Choose an issue group to review matching results.', 'wp-simple-firewall' ),
					'next_step'          => __( 'Review the results and complete the next action.', 'wp-simple-firewall' ),
					'icon_class'         => 'bi bi-list-nested',
					'badge'              => __( 'Select', 'wp-simple-firewall' ),
					'badge_status'       => 'neutral',
					'color_key'          => 'neutral',
				],
			],
		];
	}

	protected function getActiveLayerIndex() :int {
		return 0;
	}

	/**
	 * @return QueueSummary
	 */
	private function getQueueSummary() :array {
		return $this->getLandingViewData()[ 'summary' ];
	}

	/**
	 * @return array<string,ZoneGroup>
	 */
	private function getZonesIndexed() :array {
		return $this->getLandingViewData()[ 'zones_indexed' ];
	}

	private function drillDownPresentation() :ActionsQueueDrillDownPresentationBuilder {
		if ( $this->drillDownPresentation === null ) {
			$this->drillDownPresentation = new ActionsQueueDrillDownPresentationBuilder();
		}

		return $this->drillDownPresentation;
	}

}
