<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

class ActionsQueueDrillDownGroups extends ActionsQueueDrillDownRenderBase {

	public const SLUG = 'actions_queue_drill_down_groups';
	public const TEMPLATE = '/wpadmin/components/actions_queue/layer_groups.twig';

	/**
	 * @return array{
	 *   bucket_key:string,
	 *   bucket_label:string,
	 *   bucket_status:string,
	 *   bucket_item_count:int,
	 *   empty_message:string,
	 *   cta_label:string,
	 *   groups:list<array{
	 *     key:string,
	 *     label:string,
	 *     item_count:int,
	 *     status:string,
	 *     icon_class:string,
	 *     detail_shell:'asset_cards'|'direct_table'|'maintenance',
	 *     narrative:string,
	 *     next_move:string,
	 *     render_action_class:string,
	 *     render_action_data:array<string,string>,
	 *     strip_text:string,
	 *     strip_badge:string,
	 *     context:array{
	 *       path:list<string>,
	 *       focus:string,
	 *       next_step:string
	 *     }
	 *   }>,
	 *   context:array{
	 *     path:list<string>,
	 *     focus:string,
	 *     next_step:string
	 *   },
	 *   strip_text:string,
	 *   strip_badge:string,
	 *   strip_badge_status:string,
	 *   selected_group?:array{
	 *     key:string,
	 *     label:string,
	 *     item_count:int,
	 *     status:string,
	 *     icon_class:string,
	 *     detail_shell:'asset_cards'|'direct_table'|'maintenance',
	 *     narrative:string,
	 *     next_move:string,
	 *     render_action_class:string,
	 *     render_action_data:array<string,string>,
	 *     strip_text:string,
	 *     strip_badge:string,
	 *     context:array{
	 *       path:list<string>,
	 *       focus:string,
	 *       next_step:string
	 *     }
	 *   },
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
			'bucket_key'         => $groups[ 'bucket_key' ],
			'bucket_label'       => $groups[ 'bucket_label' ],
			'bucket_status'      => $groups[ 'bucket_status' ],
			'bucket_item_count'  => $groups[ 'bucket_item_count' ],
			'empty_message'      => __( 'Everything in this bucket has already been cleared.', 'wp-simple-firewall' ),
			'cta_label'          => __( 'Open results', 'wp-simple-firewall' ),
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
			$data[ 'selected_group' ] = $renderPayload[ 'selected_group' ];
		}

		return $data;
	}

	protected function getRequiredDataKeys() :array {
		return [
			'bucket',
		];
	}
}
