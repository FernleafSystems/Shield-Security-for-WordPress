<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems;

/**
 * @phpstan-import-type AttentionQuery from BuildAttentionItems
 * @phpstan-import-type ActionsQueueCardData from ActionsQueueCardDataBuilder
 * @phpstan-import-type ActionsQueueCardRow from ActionsQueueCardDataBuilder
 * @phpstan-type WpDashboardWidgetRow array{key:string,label:string,severity:string,count:int}
 * @phpstan-type WpDashboardWidgetData array{
 *   hrefs:array{cta:string},
 *   flags:array{
 *     has_items:bool,
 *     is_security_admin_restricted:bool,
 *     show_issue_count:bool,
 *     show_issue_details:bool
 *   },
 *   strings:array{
 *     status_label:string,
 *     subtitle:string,
 *     all_clear_message:string,
 *     cta:string
 *   },
 *   vars:array{
 *     shield_status:string,
 *     issue_count:int,
 *     rows:list<WpDashboardWidgetRow>
 *   }
 * }
 */
class WpDashboardSummary extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	use SecurityAdminNotRequired;

	public const SLUG = 'render_dashboard_widget';
	public const TEMPLATE = '/wpadmin/components/widget/dashboard_actions_queue.twig';

	/**
	 * @return WpDashboardWidgetData
	 */
	protected function getRenderData() :array {
		$attentionQuery = $this->buildAttentionQuery();

		return $this->isSecurityAdminRestricted()
			? $this->buildRestrictedRenderData( $attentionQuery )
			: $this->buildFullRenderData( $attentionQuery );
	}

	/**
	 * @param AttentionQuery $attentionQuery
	 * @return WpDashboardWidgetData
	 */
	private function buildFullRenderData( array $attentionQuery ) :array {
		$queueCard = $this->buildActionsQueueCardData( $attentionQuery );

		return [
			'hrefs'   => [
				'cta' => $queueCard[ 'actions_lane' ][ 'href' ],
			],
			'flags'   => [
				'has_items'                    => $queueCard[ 'summary' ][ 'has_items' ],
				'is_security_admin_restricted' => false,
				'show_issue_count'             => true,
				'show_issue_details'           => true,
			],
			'strings' => [
				'status_label'      => $queueCard[ 'actions_lane' ][ 'indicator_text' ],
				'subtitle'          => $queueCard[ 'subtitle' ],
				'all_clear_message' => __( 'No security issues currently need attention.', 'wp-simple-firewall' ),
				'cta'               => __( 'Open Actions Queue', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'shield_status' => $queueCard[ 'shield_status' ],
				'issue_count'   => $queueCard[ 'summary' ][ 'total_items' ],
				'rows'          => $this->buildWidgetRows( $queueCard[ 'actions_queue_rows' ] ),
			],
		];
	}

	/**
	 * @param AttentionQuery $attentionQuery
	 * @return WpDashboardWidgetData
	 */
	private function buildRestrictedRenderData( array $attentionQuery ) :array {
		$hasItems = !$attentionQuery[ 'summary' ][ 'is_all_clear' ];

		return [
			'hrefs'   => [
				'cta' => self::con()->plugin_urls->adminHome(),
			],
			'flags'   => [
				'has_items'                    => $hasItems,
				'is_security_admin_restricted' => true,
				'show_issue_count'             => false,
				'show_issue_details'           => false,
			],
			'strings' => [
				'status_label'      => $hasItems
					? __( 'Security Admin Attention Required', 'wp-simple-firewall' )
					: __( 'All Clear', 'wp-simple-firewall' ),
				'subtitle'          => $hasItems
					? __( 'Shield has issues that need a Security Admin to review.', 'wp-simple-firewall' )
					: __( 'No security issues currently need attention.', 'wp-simple-firewall' ),
				'all_clear_message' => __( 'No security issues currently need attention.', 'wp-simple-firewall' ),
				'cta'               => __( 'Open Shield', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'shield_status' => $hasItems ? 'warning' : 'good',
				'issue_count'   => 0,
				'rows'          => [],
			],
		];
	}

	/**
	 * @param list<ActionsQueueCardRow> $queueRows
	 * @return list<WpDashboardWidgetRow>
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
	 * @param AttentionQuery $attentionQuery
	 * @return ActionsQueueCardData
	 */
	protected function buildActionsQueueCardData( array $attentionQuery ) :array {
		return ( new ActionsQueueCardDataBuilder() )->build( $attentionQuery );
	}

	/**
	 * @return AttentionQuery
	 */
	protected function buildAttentionQuery() :array {
		return self::con()->comps->site_query->attention();
	}

	private function isSecurityAdminRestricted() :bool {
		$con = self::con();
		return $con->comps->sec_admin->isEnabledSecAdmin()
			   && !$con->this_req->is_security_admin;
	}
}
