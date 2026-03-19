<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-import-type AssessmentRow from ActionsQueueLandingAssessmentBuilder
 * @phpstan-import-type AssessmentRowsByZone from ActionsQueueLandingAssessmentBuilder
 * @phpstan-type ZoneTile array{
 *   key:string,
 *   panel_target:string,
 *   is_enabled:bool,
 *   is_disabled:bool,
 *   has_issues:bool,
 *   has_assessments:bool,
 *   has_panel_content:bool,
 *   label:string,
 *   icon_class:string,
 *   status:string,
 *   status_label:string,
 *   total_issues:int,
 *   critical_count:int,
 *   warning_count:int,
 *   summary_text:string,
 *   items:list<array<string,mixed>>,
 *   assessment_rows:list<AssessmentRow>,
 *   maintenance_detail_groups?:list<array{status:string,rows:list<array<string,mixed>>}>
 * }
 * @phpstan-import-type RawDrillLayer from PageDrillDownLandingBase
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
			'queue_is_empty' => !$this->getQueueSummary()[ 'has_items' ],
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
			'status_action_required' => __( 'Action Required', 'wp-simple-firewall' ),
			'status_all_clear'       => __( 'All Clear', 'wp-simple-firewall' ),
			'severity_strip_label'   => __( 'Queue Status', 'wp-simple-firewall' ),
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

	protected function getLandingVars() :array {
		$viewData = $this->getLandingViewData();

		return \array_merge( parent::getLandingVars(), [
			'severity_strip'     => $viewData[ 'severity_strip' ],
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
				],
			],
			[
				'key'    => 'groups',
				'body'   => '',
				'header' => [
					'compact_back_label' => $presentation->buildBackLabel( __( 'Grouped Findings', 'wp-simple-firewall' ) ),
					'active_back_label'  => $presentation->buildBackLabel( __( 'Actions Queue', 'wp-simple-firewall' ) ),
					'title'              => __( 'Grouped findings', 'wp-simple-firewall' ),
					'summary'            => __( 'Choose a bucket to start.', 'wp-simple-firewall' ),
					'icon_class'         => 'bi bi-list-ul',
					'badge'              => __( 'Select', 'wp-simple-firewall' ),
					'badge_status'       => 'neutral',
				],
			],
			[
				'key'    => 'detail',
				'body'   => '',
				'header' => [
					'compact_back_label' => $presentation->buildBackLabel( __( 'Scoped Results', 'wp-simple-firewall' ) ),
					'active_back_label'  => $presentation->buildBackLabel( __( 'Grouped Findings', 'wp-simple-firewall' ) ),
					'title'              => __( 'Scoped results', 'wp-simple-firewall' ),
					'summary'            => __( 'Choose a group to review the matching results.', 'wp-simple-firewall' ),
					'icon_class'         => 'bi bi-list-nested',
					'badge'              => __( 'Select', 'wp-simple-firewall' ),
					'badge_status'       => 'neutral',
				],
			],
		];
	}

	protected function getActiveLayerIndex() :int {
		return 0;
	}

	/**
	 * @return array{
	 *   has_items:bool,
	 *   total_items:int,
	 *   severity:string,
	 *   icon_class:string,
	 *   subtext:string
	 * }
	 */
	private function getQueueSummary() :array {
		return $this->getLandingViewData()[ 'summary' ];
	}

	/**
	 * @return array<string,array{
	 *   slug:string,
	 *   label:string,
	 *   icon_class:string,
	 *   severity:string,
	 *   total_issues:int,
	 *   items:list<array<string,mixed>>
	 * }>
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

	private function encodeJson( array $data ) :string {
		return (string)( \json_encode( $data ) ?: '' );
	}
}
