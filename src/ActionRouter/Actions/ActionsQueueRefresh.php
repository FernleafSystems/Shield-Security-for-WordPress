<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueGroupsBuilder,
	BuildsActionsQueueLandingData
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\NavMenuBuilder;

/**
 * After something in the Actions Queue changes (an item ignored, a result fixed, a protection turned on), the
 * queue refreshes in place: the landing's lanes, its summary, and the sidebar's counts. With results open, the
 * open group comes too, so its heading follows.
 */
class ActionsQueueRefresh extends BaseAction {

	use BuildsActionsQueueLandingData;
	use Traits\NonceVerifyRequired;

	public const SLUG = 'actions_queue_refresh';

	protected function exec() {
		$payload = [
			'landing_refresh' => $this->buildLandingRefresh(),
		];

		$groupKey = \trim( (string)( $this->action_data[ 'group' ] ?? '' ) );
		if ( $groupKey !== '' ) {
			$payload[ 'selected_group' ] = ( new ActionsQueueGroupsBuilder() )->buildGroup(
				(string)$this->action_data[ 'bucket' ],
				$groupKey,
				$this->getAttentionQuery(),
				$this->getAssessmentRowsByZone()
			)[ 'selection' ];
		}

		$this->response()->setPayload( $payload )->setPayloadSuccess( true );
	}

	/**
	 * @return array{
	 *   has_drilldown_content:bool,
	 *   root_step_json:string,
	 *   buckets_html:string,
	 *   nav_status:string,
	 *   nav_badges_html:string
	 * }
	 */
	private function buildLandingRefresh() :array {
		$hasDrilldownContent = $this->hasDrilldownContent();
		$navState = ( new NavMenuBuilder() )->buildActionsQueueNavState();
		return [
			'has_drilldown_content' => $hasDrilldownContent,
			'root_step_json'        => $this->buildActionsQueueOperatorRootStepJson(),
			'buckets_html'          => $hasDrilldownContent ? $this->renderBucketsLayer() : '',
			'nav_status'            => $navState[ 'status' ],
			'nav_badges_html'       => self::con()->comps->render
				->setTemplate( '/wpadmin/components/page/mode_badges.twig' )
				->setData( [ 'badges' => $navState[ 'badges' ] ] )
				->render(),
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'bucket',
		];
	}
}
