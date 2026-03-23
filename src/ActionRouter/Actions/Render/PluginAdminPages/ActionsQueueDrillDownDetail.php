<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-import-type GroupSelection from ActionsQueueDrillDownPresentationBuilder
 * @phpstan-import-type DrillLayerHeader from OperatorChromeContract
 */
class ActionsQueueDrillDownDetail extends DrillDownAjaxRenderBase {

	use BuildsActionsQueueLandingData;

	public const SLUG = 'actions_queue_drill_down_detail';
	public const TEMPLATE = '/wpadmin/components/actions_queue/layer_detail.twig';

	/**
	 * @return array{
	 *   group_selection:GroupSelection,
	 *   detail_html:string,
	 *   header:DrillLayerHeader
	 * }
	 */
	protected function getRenderData() :array {
		$group = ( new ActionsQueueGroupsBuilder() )->buildGroup(
			$this->action_data[ 'bucket' ],
			$this->action_data[ 'group' ],
			$this->getAttentionQuery(),
			$this->getAssessmentRowsByZone()
		);
		$detailHtml = !empty( $group[ 'detail_table' ] )
			? $this->renderDetailTable( $group[ 'detail_table' ] )
			: self::con()->action_router->action(
				$group[ 'render_action_class' ],
				$group[ 'render_action_data' ]
			)->payload()[ 'html' ];

		return [
			'group_selection' => $group[ 'selection' ],
			'detail_html'     => $detailHtml,
			'header'          => $group[ 'selection' ][ 'header' ],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'bucket',
			'group',
		];
	}

	protected function promotedRenderDataKeys() :array {
		return [
			'group_selection',
		];
	}

	/**
	 * @param array<string,mixed> $table
	 */
	private function renderDetailTable( array $table ) :string {
		return self::con()
			->comps
			->render
			->setTemplate( '/wpadmin/components/investigate/table_container.twig' )
			->setData( [
				'table' => $table,
			] )
			->setEnvironmentVars( $this->getTwigEnvironmentVars() )
			->render();
	}
}
