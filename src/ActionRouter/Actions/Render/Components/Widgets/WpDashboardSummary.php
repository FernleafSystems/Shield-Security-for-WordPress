<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AnyUserAuthRequired;

/**
 * @phpstan-import-type ActionsQueueCardData from ActionsQueueCardDataBuilder
 * @phpstan-import-type ActionsQueueCardRow from ActionsQueueCardDataBuilder
 */
class WpDashboardSummary extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	use AnyUserAuthRequired;

	public const SLUG = 'render_dashboard_widget';
	public const TEMPLATE = '/wpadmin/components/widget/dashboard_actions_queue.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$queueCard = $this->buildActionsQueueCardData();

		return [
			'hrefs'   => [
				'actions_queue' => $queueCard[ 'actions_lane' ][ 'href' ],
			],
			'flags'   => [
				'has_items'           => $queueCard[ 'summary' ][ 'has_items' ],
				'show_internal_links' => $con->isPluginAdmin(),
			],
			'strings' => [
				'status_label'       => $queueCard[ 'actions_lane' ][ 'indicator_text' ],
				'subtitle'           => $queueCard[ 'subtitle' ],
				'all_clear_message'  => __( 'No security issues currently need attention.', 'wp-simple-firewall' ),
				'open_actions_queue' => __( 'Open Actions Queue', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'shield_status' => $queueCard[ 'shield_status' ],
				'summary'       => $queueCard[ 'summary' ],
				'rows'          => $this->buildWidgetRows( $queueCard[ 'actions_queue_rows' ] ),
			],
		];
	}

	/**
	 * @param list<ActionsQueueCardRow> $queueRows
	 * @return list<array{key:string,label:string,severity:string,count:int}>
	 */
	private function buildWidgetRows( array $queueRows ) :array {
		return \array_map(
			static fn( array $row ) :array => [
				'key'      => $row[ 'key' ],
				'label'    => $row[ 'label' ],
				'severity' => $row[ 'severity' ],
				'count'    => $row[ 'count' ],
			],
			$queueRows
		);
	}

	/**
	 * @return ActionsQueueCardData
	 */
	protected function buildActionsQueueCardData() :array {
		return ( new ActionsQueueCardDataBuilder() )->build(
			$this->buildAttentionQuery(),
			$this->buildScanState()[ 'rows' ]
		);
	}

	protected function buildAttentionQuery() :array {
		return self::con()->comps->site_query->attention();
	}

	protected function buildScanState() :array {
		return ( new ActionsQueueScanStateBuilder() )->build();
	}
}
