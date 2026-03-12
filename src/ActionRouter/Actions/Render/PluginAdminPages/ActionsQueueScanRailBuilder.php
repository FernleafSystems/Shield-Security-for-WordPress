<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	ActionsQueueScanRailMetrics,
	AjaxBatchRequests
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\{
	FileLocker,
	Malware,
	Plugins,
	Themes,
	Vulnerabilities,
	Wordpress
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueuePayload;

/**
 * @phpstan-import-type QueueItem from NeedsAttentionQueuePayload
 * @phpstan-import-type ZoneGroup from NeedsAttentionQueuePayload
 * @phpstan-type AssessmentRow array{
 *   key:string,
 *   label:string,
 *   description:string,
 *   status:string,
 *   status_label:string,
 *   status_icon_class:string
 * }
 * @phpstan-type SummaryRow array{
 *   key:string,
 *   label:string,
 *   text:string,
 *   severity:string,
 *   count:int,
 *   action:string,
 *   href:string
 * }
 * @phpstan-type RailMetrics array{
 *   tabs:array<string,array{count:int,status:string}>,
 *   rail_accent_status:string
 * }
 */

class ActionsQueueScanRailBuilder extends ScansResultsViewBuilder {

	/**
	 * @param list<AssessmentRow> $assessmentRows
	 * @return array<string,mixed>
	 */
	public function buildFromLandingData( array $needsAttentionPayload, array $assessmentRows = [] ) :array {
		$scansZoneGroup = NeedsAttentionQueuePayload::zoneGroup( $needsAttentionPayload, 'scans' );
		$summaryRows = $this->buildSummaryRowsFromZoneGroup( $scansZoneGroup );
		$metrics = $this->buildInitialRailMetrics();
		$summaryMetrics = $metrics[ 'tabs' ][ 'summary' ];
		$lazyDefinitions = $this->buildLazyTabDefinitions();
		$summaryMeta = $this->getRailTabMeta( 'summary' );
		$railTabs = $this->buildTabs( \array_merge( [
			[
				'key'        => 'summary',
				'label'      => $summaryMeta[ 'label' ],
				'count'      => $summaryMetrics[ 'count' ],
				'is_shown'   => true,
				'status'     => $summaryMetrics[ 'status' ],
				'icon_class' => $summaryMeta[ 'icon_class' ],
				'items'      => $this->buildSummaryRailItems(
					$summaryRows,
					$assessmentRows,
					$this->buildSummaryRailTargets( $lazyDefinitions )
				),
				'is_loaded'  => true,
			],
		], $lazyDefinitions ) );

		$rail = $this->buildRailContract( $railTabs );
		$rail[ 'accent_status' ] = $metrics[ 'rail_accent_status' ];

		return [
			'strings' => [
				'pane_loading' => __( 'Loading scan details...', 'wp-simple-firewall' ),
				'no_issues'    => __( 'No issues found in this section.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'rail'            => $rail,
				'rail_tabs'       => $railTabs,
				'metrics_action'  => ActionData::Build( ActionsQueueScanRailMetrics::class ),
				'preload_action'  => ActionData::Build( AjaxBatchRequests::class ),
				'summary_rows'    => $summaryRows,
				'assessment_rows' => $assessmentRows,
			],
			'content' => [
				'section' => [
					'wordpress'       => '',
					'plugins'         => '',
					'themes'          => '',
					'vulnerabilities' => '',
					'malware'         => '',
					'filelocker'      => '',
				],
			],
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	public function buildVulnerabilitiesPane() :array {
		return $this->buildRailPaneData( 'vulnerabilities' );
	}

	/**
	 * @return RailMetrics
	 */
	protected function buildInitialRailMetrics() :array {
		return ( new ActionsQueueScanRailMetricsBuilder() )->build();
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function buildLazyTabDefinitions() :array {
		$definitions = [];
		foreach ( $this->getOrderedRailTabKeys( false ) as $tabKey ) {
			$definition = $this->buildLazyTabDefinition( $tabKey );
			if ( $definition !== null ) {
				$definitions[] = $definition;
			}
		}
		return $definitions;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function buildLazyTabDefinition( string $tabKey ) :?array {
		$availability = $this->getRailTabAvailability( $tabKey );
		if ( !$availability[ 'show_in_actions_queue' ] ) {
			return null;
		}

		$meta = $this->getRailTabMeta( $tabKey );
		$definition = [
			'key'        => $tabKey,
			'label'      => $meta[ 'label' ],
			'count'      => null,
			'is_shown'   => true,
			'status'     => 'neutral',
			'icon_class' => $meta[ 'icon_class' ],
			'items'      => [],
			'is_loaded'  => false,
			'is_disabled' => !$availability[ 'is_available' ],
			'disabled_message' => $availability[ 'disabled_message' ],
			'disabled_status' => $availability[ 'disabled_status' ],
			'render_action' => [],
			'show_count_placeholder' => true,
		];

		switch ( $tabKey ) {
			case 'wordpress':
				return \array_merge( $definition, [ 'render_action' => $this->buildAjaxRenderActionData( Wordpress::class ) ] );

			case 'plugins':
				return \array_merge( $definition, [ 'render_action' => $this->buildAjaxRenderActionData( Plugins::class, [
					'display_context' => 'actions_queue',
				] ) ] );

			case 'themes':
				return \array_merge( $definition, [ 'render_action' => $this->buildAjaxRenderActionData( Themes::class, [
					'display_context' => 'actions_queue',
				] ) ] );

			case 'vulnerabilities':
				return \array_merge( $definition, [ 'render_action' => $this->buildAjaxRenderActionData( Vulnerabilities::class ) ] );

			case 'malware':
				return \array_merge( $definition, [ 'render_action' => $this->buildAjaxRenderActionData( Malware::class ) ] );

			case 'file_locker':
				return \array_merge( $definition, [ 'render_action' => $this->buildAjaxRenderActionData( FileLocker::class, [
					'display_context' => 'actions_queue',
				] ) ] );

			default:
				return null;
		}
	}

	/**
	 * @param ZoneGroup $scansZoneGroup
	 * @return list<SummaryRow>
	 */
	private function buildSummaryRowsFromZoneGroup( array $scansZoneGroup ) :array {
		return \array_values( \array_map( static function ( array $item ) :array {
			return [
				'key'      => $item[ 'key' ],
				'label'    => $item[ 'label' ],
				'text'     => $item[ 'description' ],
				'severity' => $item[ 'severity' ],
				'count'    => $item[ 'count' ],
				'action'   => $item[ 'action' ],
				'href'     => $item[ 'href' ],
			];
		}, $scansZoneGroup[ 'items' ] ) );
	}
}
