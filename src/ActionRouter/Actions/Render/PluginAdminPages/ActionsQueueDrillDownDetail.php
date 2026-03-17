<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

class ActionsQueueDrillDownDetail extends ActionsQueueDrillDownRenderBase {

	public const SLUG = 'actions_queue_drill_down_detail';
	public const TEMPLATE = '/wpadmin/components/actions_queue/layer_detail.twig';

	/**
	 * @return array{
	 *   bucket_key:string,
	 *   group_key:string,
	 *   group_label:string,
	 *   group_status:string,
	 *   group_item_count:int,
	 *   group_detail_shell:'asset_cards'|'direct_table'|'maintenance',
	 *   detail_html:string,
	 *   context:array{
	 *     path:list<string>,
	 *     focus:string,
	 *     next_step:string
	 *   },
	 *   strip_text:string,
	 *   strip_badge:string,
	 *   strip_badge_status:string
	 * }
	 */
	protected function getRenderData() :array {
		$group = ( new ActionsQueueGroupsBuilder() )->buildGroup(
			$this->action_data[ 'bucket' ],
			$this->action_data[ 'group' ],
			$this->getAttentionQuery(),
			$this->getAssessmentRowsByZone()
		);
		$payload = self::con()->action_router->action(
			$group[ 'render_action_class' ],
			$group[ 'render_action_data' ]
		)->payload();

		return [
			'bucket_key'         => $this->action_data[ 'bucket' ],
			'group_key'          => $group[ 'key' ],
			'group_label'        => $group[ 'label' ],
			'group_status'       => $group[ 'status' ],
			'group_item_count'   => $group[ 'item_count' ],
			'group_detail_shell' => $group[ 'detail_shell' ],
			'detail_html'        => $payload[ 'html' ],
			'context'            => $group[ 'context' ],
			'strip_text'         => $group[ 'strip_text' ],
			'strip_badge'        => $group[ 'strip_badge' ],
			'strip_badge_status' => $group[ 'status' ],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'bucket',
			'group',
		];
	}
}
