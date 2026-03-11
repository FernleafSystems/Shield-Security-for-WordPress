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
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

class ActionsQueueScanRailBuilder extends ScansResultsViewBuilder {

	/**
	 * @param list<array<string,mixed>> $assessmentRows
	 * @return array<string,mixed>
	 */
	public function buildFromLandingData( array $needsAttentionPayload, array $assessmentRows = [] ) :array {
		$scansZoneGroup = $this->getScansZoneGroup( $needsAttentionPayload );
		$summaryRows = $this->buildSummaryRowsFromZoneGroup( $scansZoneGroup );
		$lazyDefinitions = $this->buildLazyTabDefinitions();
		$summaryMeta = $this->getRailTabMeta( 'summary' );
		$railTabs = $this->buildTabs( \array_merge( [
			[
				'key'        => 'summary',
				'label'      => $summaryMeta[ 'label' ],
				'count'      => \count( $summaryRows ),
				'status'     => $this->buildSummaryStatus( $summaryRows, $assessmentRows ),
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
		$rail[ 'accent_status' ] = StatusPriority::normalize(
			(string)( $scansZoneGroup[ 'severity' ] ?? 'good' ),
			'good'
		);

		return [
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
		if ( empty( $availability[ 'show_in_actions_queue' ] ) ) {
			return null;
		}

		$meta = $this->getRailTabMeta( $tabKey );
		$definition = [
			'key'        => $tabKey,
			'label'      => $meta[ 'label' ],
			'count'      => null,
			'status'     => 'neutral',
			'icon_class' => $meta[ 'icon_class' ],
			'items'      => [],
			'is_loaded'  => false,
			'show_count_placeholder' => true,
		];

		switch ( $tabKey ) {
			case 'wordpress':
				return \array_merge( $definition, [ 'render_action' => $this->buildAjaxRenderActionData( Wordpress::class ) ] );

			case 'plugins':
				return \array_merge( $definition, [ 'render_action' => $this->buildAjaxRenderActionData( Plugins::class ) ] );

			case 'themes':
				return \array_merge( $definition, [ 'render_action' => $this->buildAjaxRenderActionData( Themes::class ) ] );

			case 'vulnerabilities':
				return \array_merge( $definition, [ 'render_action' => $this->buildAjaxRenderActionData( Vulnerabilities::class ) ] );

			case 'malware':
				return \array_merge( $definition, [ 'render_action' => $this->buildAjaxRenderActionData( Malware::class ) ] );

			case 'file_locker':
				return \array_merge( $definition, [ 'render_action' => $this->buildAjaxRenderActionData( FileLocker::class ) ] );

			default:
				return null;
		}
	}

	/**
	 * @param list<array<string,mixed>> $summaryRows
	 * @param list<array<string,mixed>> $assessmentRows
	 */
	private function buildSummaryStatus( array $summaryRows, array $assessmentRows ) :string {
		if ( !empty( $summaryRows ) ) {
			return StatusPriority::highest(
				\array_map(
					static fn( array $row ) :string => StatusPriority::normalize(
						(string)( $row[ 'severity' ] ?? 'warning' ),
						'warning'
					),
					$summaryRows
				),
				'good'
			);
		}

		return StatusPriority::highest(
			\array_map(
				static fn( array $row ) :string => StatusPriority::normalize(
					(string)( $row[ 'status' ] ?? 'good' ),
					'good'
				),
				$assessmentRows
			),
			'good'
		);
	}

	/**
	 * @return array{slug:string,label:string,icon_class:string,severity:string,total_issues:int,items:list<array<string,mixed>>}
	 */
	private function getScansZoneGroup( array $needsAttentionPayload ) :array {
		foreach ( NeedsAttentionQueuePayload::zoneGroups( $needsAttentionPayload ) as $zoneGroup ) {
			if ( \sanitize_key( (string)( $zoneGroup[ 'slug' ] ?? '' ) ) === 'scans' ) {
				return $zoneGroup;
			}
		}

		return [
			'slug'         => 'scans',
			'label'        => '',
			'icon_class'   => '',
			'severity'     => 'good',
			'total_issues' => 0,
			'items'        => [],
		];
	}

	/**
	 * @param array{items?:list<array<string,mixed>>} $scansZoneGroup
	 * @return list<array<string,mixed>>
	 */
	private function buildSummaryRowsFromZoneGroup( array $scansZoneGroup ) :array {
		return \array_values( \array_map( static function ( array $item ) :array {
			return [
				'key'      => (string)( $item[ 'key' ] ?? '' ),
				'label'    => (string)( $item[ 'label' ] ?? '' ),
				'text'     => (string)( $item[ 'description' ] ?? '' ),
				'severity' => StatusPriority::normalize( (string)( $item[ 'severity' ] ?? 'warning' ), 'warning' ),
				'count'    => \max( 0, (int)( $item[ 'count' ] ?? 0 ) ),
				'action'   => (string)( $item[ 'action' ] ?? '' ),
				'href'     => (string)( $item[ 'href' ] ?? '' ),
			];
		}, \is_array( $scansZoneGroup[ 'items' ] ?? null ) ? \array_values( $scansZoneGroup[ 'items' ] ) : [] ) );
	}
}
