<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-import-type BucketSelection from ActionsQueueDrillDownPresentationBuilder
 * @phpstan-import-type DrillLayerHeader from OperatorChromeContract
 * @phpstan-import-type GroupSectionData from ActionsQueueGroupsBuilder
 * @phpstan-import-type GroupSelection from ActionsQueueDrillDownPresentationBuilder
 */
class ActionsQueueDrillDownGroups extends DrillDownAjaxRenderBase {

	use BuildsActionsQueueLandingData;

	public const SLUG = 'actions_queue_drill_down_groups';
	public const TEMPLATE = '/wpadmin/components/actions_queue/layer_groups.twig';

	/**
	 * @return array{
	 *   bucket_selection:BucketSelection,
	 *   empty_message:string,
	 *   active_sections:list<GroupSectionData>,
	 *   healthy_sections:list<GroupSectionData>,
	 *   header:DrillLayerHeader,
	 *   selected_group?:GroupSelection,
	 *   landing_refresh?:array{
	 *     has_drilldown_content:bool,
	 *     root_step_json:string,
	 *     buckets_html:string
	 *   }
	 * }
	 */
	protected function getRenderData() :array {
		$attentionQuery = $this->getAttentionQuery();
		$assessmentRowsByZone = $this->getAssessmentRowsByZone();
		$builder = new ActionsQueueGroupsBuilder();
		$groupKey = \trim( $this->action_data[ 'group' ] ?? '' );
		$renderPayload = $groupKey === ''
			? [
				'layer' => $builder->build(
					$this->action_data[ 'bucket' ],
					$attentionQuery,
					$assessmentRowsByZone
				),
			]
			: $builder->buildWithSelectedGroup(
				$this->action_data[ 'bucket' ],
				$groupKey,
				$attentionQuery,
				$assessmentRowsByZone
			);
		$groups = $renderPayload[ 'layer' ];

		$data = [
			'bucket_selection' => $groups[ 'bucket_selection' ],
			'empty_message'    => __( 'Everything in this bucket has already been cleared.', 'wp-simple-firewall' ),
			'active_sections'  => $groups[ 'active_sections' ],
			'healthy_sections' => $groups[ 'healthy_sections' ],
			'header'           => $groups[ 'header' ],
		];
		if ( !empty( $this->action_data[ 'include_landing_refresh' ] ) ) {
			$hasDrilldownContent = $this->hasDrilldownContent();
			$data[ 'landing_refresh' ] = [
				'has_drilldown_content'  => $hasDrilldownContent,
				'root_step_json'         => $this->buildActionsQueueOperatorRootStepJson(),
				'buckets_html'           => $hasDrilldownContent ? $this->renderBucketsLayer() : '',
			];
		}
		if ( isset( $renderPayload[ 'selected_group' ] ) ) {
			$data[ 'selected_group' ] = $renderPayload[ 'selected_group' ][ 'selection' ];
		}

		return $data;
	}

	protected function getRequiredDataKeys() :array {
		return [
			'bucket',
		];
	}

	protected function promotedRenderDataKeys() :array {
		return [
			'bucket_selection',
			'selected_group',
			'landing_refresh',
		];
	}
}
