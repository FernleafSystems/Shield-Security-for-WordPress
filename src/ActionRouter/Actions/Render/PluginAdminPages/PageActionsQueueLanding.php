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
		$allClear = $this->getLandingViewData()[ 'all_clear' ];

		return [
			'all_clear_title'        => $allClear[ 'title' ],
			'all_clear_subtitle'     => $allClear[ 'subtitle' ],
			'all_clear_icon_class'   => $allClear[ 'icon_class' ],
			'zone_scans'             => $zones[ 'scans' ][ 'label' ],
			'zone_maintenance'       => $zones[ 'maintenance' ][ 'label' ],
			'pane_loading'           => __( 'Loading scan details...', 'wp-simple-firewall' ),
			'groups_loading'         => __( 'Loading grouped findings...', 'wp-simple-firewall' ),
			'detail_loading'         => __( 'Loading scoped results...', 'wp-simple-firewall' ),
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

		return \array_merge( parent::getLandingVars(), [
			'zone_tiles'         => $viewData[ 'zone_tiles' ],
			'all_clear'          => $viewData[ 'all_clear' ],
			'actions_queue_ajax' => [
				'groups_render_action' => ActionData::BuildAjaxRender( ActionsQueueDrillDownGroups::class ),
				'groups_render_action_json' => $this->encodeJson(
					ActionData::BuildAjaxRender( ActionsQueueDrillDownGroups::class )
				),
				'detail_render_action' => ActionData::BuildAjaxRender( ActionsQueueDrillDownDetail::class ),
				'detail_render_action_json' => $this->encodeJson(
					ActionData::BuildAjaxRender( ActionsQueueDrillDownDetail::class )
				),
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
					'breadcrumb_label'   => __( 'Buckets', 'wp-simple-firewall' ),
					'title'              => __( 'Buckets', 'wp-simple-firewall' ),
					'summary'            => __( 'Choose the queue area that needs attention first.', 'wp-simple-firewall' ),
					'next_step'          => __( 'Open a bucket to continue into grouped findings.', 'wp-simple-firewall' ),
					'icon_class'         => 'bi bi-inboxes',
					'badge_status'       => $summary[ 'severity' ],
					'color_key'          => $summary[ 'severity' ],
				],
			],
			[
				'key'    => 'groups',
				'body'   => '',
				'header' => [
					'compact_back_label' => $presentation->buildBackLabel( __( 'Grouped Findings', 'wp-simple-firewall' ) ),
					'active_back_label'  => $presentation->buildBackLabel( __( 'Actions Queue', 'wp-simple-firewall' ) ),
					'breadcrumb_label'   => __( 'Grouped findings', 'wp-simple-firewall' ),
					'title'              => __( 'Grouped findings', 'wp-simple-firewall' ),
					'summary'            => __( 'Choose a bucket to start.', 'wp-simple-firewall' ),
					'next_step'          => __( 'Choose one group to review the matching results.', 'wp-simple-firewall' ),
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
					'compact_back_label' => $presentation->buildBackLabel( __( 'Scoped Results', 'wp-simple-firewall' ) ),
					'active_back_label'  => $presentation->buildBackLabel( __( 'Grouped Findings', 'wp-simple-firewall' ) ),
					'breadcrumb_label'   => __( 'Scoped results', 'wp-simple-firewall' ),
					'title'              => __( 'Scoped results', 'wp-simple-firewall' ),
					'summary'            => __( 'Choose a group to review the matching results.', 'wp-simple-firewall' ),
					'next_step'          => __( 'Review the scoped results and complete the next action.', 'wp-simple-firewall' ),
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
