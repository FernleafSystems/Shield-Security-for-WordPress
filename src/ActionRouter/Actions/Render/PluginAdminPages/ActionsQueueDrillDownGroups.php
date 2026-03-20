<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-import-type BucketSelection from ActionsQueueDrillDownPresentationBuilder
 * @phpstan-import-type GroupData from ActionsQueueGroupsBuilder
 * @phpstan-import-type GroupSelection from ActionsQueueDrillDownPresentationBuilder
 */
class ActionsQueueDrillDownGroups extends ActionsQueueDrillDownRenderBase {

	public const SLUG = 'actions_queue_drill_down_groups';
	public const TEMPLATE = '/wpadmin/components/actions_queue/layer_groups.twig';

	/**
	 * @return array{
	 *   bucket_selection:BucketSelection,
	 *   empty_message:string,
	 *   groups:list<GroupData>,
	 *   header:array<string,string>,
 *   selected_group?:GroupSelection,
 *   landing_refresh?:array{
 *     queue_is_empty:bool,
 *     has_drilldown_content:bool,
 *     root_step_json:string,
 *     buckets_html:string,
 *     all_clear_html:string
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
			'groups'           => $groups[ 'groups' ],
			'header'           => $groups[ 'header' ],
		];
		if ( !empty( $this->action_data[ 'include_landing_refresh' ] ) ) {
			$landingView = $this->getLandingViewData();
			$isQueueEmpty = !$landingView[ 'summary' ][ 'has_items' ];
			$hasDrilldownContent = $this->hasDrilldownContent();
			$data[ 'landing_refresh' ] = [
				'queue_is_empty'         => $isQueueEmpty,
				'has_drilldown_content'  => $hasDrilldownContent,
				'root_step_json'         => (string)( \json_encode( $this->buildActionsQueueOperatorRootStep() ) ?: '' ),
				'buckets_html'           => $hasDrilldownContent ? $this->renderBucketsLayer() : '',
				'all_clear_html'         => $isQueueEmpty
					? $this->renderAllClearCard()
					: '',
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
}
