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
	 *   bucket_selection_json:string,
	 *   empty_message:string,
	 *   groups:list<GroupData>,
	 *   context:array{
	 *     path:list<string>,
	 *     focus:string,
	 *     next_step:string
	 *   },
	 *   strip_text:string,
	 *   strip_badge:string,
	 *   strip_badge_status:string,
	 *   selected_group?:GroupSelection,
	 *   landing_refresh?:array{
	 *     queue_is_empty:bool,
	 *     severity_strip_html:string,
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
			'bucket_selection'   => $groups[ 'bucket_selection' ],
			'bucket_selection_json' => $groups[ 'bucket_selection_json' ],
			'empty_message'      => __( 'Everything in this bucket has already been cleared.', 'wp-simple-firewall' ),
			'groups'             => $groups[ 'groups' ],
			'context'            => $groups[ 'context' ],
			'strip_text'         => $groups[ 'strip_text' ],
			'strip_badge'        => $groups[ 'strip_badge' ],
			'strip_badge_status' => $groups[ 'strip_badge_status' ],
		];
		if ( !empty( $this->action_data[ 'include_landing_refresh' ] ) ) {
			$landingView = $this->getLandingViewData();
			$isQueueEmpty = !$landingView[ 'summary' ][ 'has_items' ];
			$data[ 'landing_refresh' ] = [
				'queue_is_empty'      => $isQueueEmpty,
				'severity_strip_html' => $this->renderSeverityStripSection(),
				'buckets_html'        => $this->renderBucketsLayer(),
				'all_clear_html'      => $isQueueEmpty
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
