<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-import-type GroupSelection from ActionsQueueDrillDownPresentationBuilder
 */
class ActionsQueueDrillDownDetail extends ActionsQueueDrillDownRenderBase {

	public const SLUG = 'actions_queue_drill_down_detail';
	public const TEMPLATE = '/wpadmin/components/actions_queue/layer_detail.twig';

	/**
	 * @return array{
	 *   group_selection:GroupSelection,
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
			'group_selection'    => $group[ 'selection' ],
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
