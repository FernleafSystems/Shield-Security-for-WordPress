<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Zones\ZoneRenderDataBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;
use FernleafSystems\Wordpress\Services\Services;

class NeedsAttentionQueue extends BaseRender {

	public const SLUG = 'render_widget_needs_attention_queue';
	public const TEMPLATE = '/wpadmin/components/widget/needs_attention_queue.twig';
	private ?ZoneRenderDataBuilder $zoneRenderDataBuilder = null;

	protected function getRenderData() :array {
		$scansCon = self::con()->comps->scans;
		$provider = new AttentionItemsProvider();
		$items = $provider->buildQueueItems();
		$totalItems = (int)\array_sum( \array_column( $items, 'count' ) );

		$zoneGroups = $this->buildZoneGroups( $items );
		$hasItems = !empty( $items );

		$latestScanAt = $provider->getLatestCompletedScanTimestamp( $scansCon->getScanSlugs() );
		$lastScanSubtext = $latestScanAt > 0
			? sprintf(
				__( 'Last scan: %s', 'wp-simple-firewall' ),
				Services::Request()->carbon( true )->setTimestamp( $latestScanAt )->diffForHumans()
			)
			: '';
		$statusStripText = $hasItems
			? sprintf(
				_n( '%s issue needs your attention', '%s issues need your attention', $totalItems, 'wp-simple-firewall' ),
				$totalItems
			)
			: __( 'Your site is secure', 'wp-simple-firewall' );
		$summary = $this->buildQueueSummaryContract( $hasItems, $totalItems, $items, $lastScanSubtext );

		return [
			'flags'   => [
				'has_items' => $summary[ 'has_items' ],
			],
			'strings' => [
				'status_strip_icon_class' => $summary[ 'icon_class' ],
				'status_strip_text'       => $statusStripText,
				'status_strip_subtext'    => $summary[ 'subtext' ],
				'title'                   => __( 'Action Required', 'wp-simple-firewall' ),
				'issues_found'            => __( 'Actions Required', 'wp-simple-firewall' ),
				'all_clear'               => __( 'All Clear', 'wp-simple-firewall' ),
				'all_clear_icon_class'    => self::con()->svgs->iconClass( 'shield-check' ),
				'all_clear_title'         => __( 'All security zones are clear', 'wp-simple-firewall' ),
				'all_clear_subtitle'      => __( 'Shield is actively protecting your site. Nothing requires your action.', 'wp-simple-firewall' ),
				'all_clear_message'       => __( 'No security actions currently require your attention.', 'wp-simple-firewall' ),
				'last_scan_subtext'       => $lastScanSubtext,
			],
			'vars'    => [
				'summary'          => $summary,
				'overall_severity' => $summary[ 'severity' ],
				'total_items'      => $summary[ 'total_items' ],
				'zone_groups'      => \array_values( $zoneGroups ),
				'zone_chips'       => $this->buildAllClearZoneChips(),
			],
		];
	}

	private function buildZoneGroups( array $items ) :array {
		$groups = [];

		foreach ( $items as $item ) {
			$zone = $item[ 'zone' ];
			if ( !isset( $groups[ $zone ] ) ) {
				$zoneData = $this->zoneDataFor( $zone );
				$groups[ $zone ] = [
					'slug'         => $zone,
					'label'        => $zoneData[ 'label' ],
					'icon_class'   => $zoneData[ 'icon_class' ],
					'severity'     => 'good',
					'total_issues' => 0,
					'items'        => [],
				];
			}
			$groups[ $zone ][ 'items' ][] = $item;
			$groups[ $zone ][ 'total_issues' ] += $item[ 'count' ];
			$groups[ $zone ][ 'severity' ] = StatusPriority::highest( [
				$groups[ $zone ][ 'severity' ],
				$item[ 'severity' ],
			], 'good' );
		}

		return $groups;
	}

	private function determineOverallSeverity( array $items ) :string {
		if ( empty( $items ) ) {
			return 'good';
		}
		return StatusPriority::highest( \array_column( $items, 'severity' ), 'good' );
	}

	/**
	 * @return array{
	 *   has_items:bool,
	 *   total_items:int,
	 *   severity:string,
	 *   icon_class:string,
	 *   subtext:string
	 * }
	 */
	private function buildQueueSummaryContract( bool $hasItems, int $totalItems, array $items, string $lastScanSubtext ) :array {
		return [
			'has_items'   => $hasItems,
			'total_items' => $totalItems,
			'severity'    => $this->determineOverallSeverity( $items ),
			'icon_class'  => self::con()->svgs->iconClass( $hasItems ? 'exclamation-triangle-fill' : 'shield-check' ),
			'subtext'     => $lastScanSubtext,
		];
	}

	private function buildAllClearZoneChips() :array {
		$chips = [];
		$zonesData = $this->getZonesData();
		$chipIconClass = self::con()->svgs->iconClass( 'check-circle-fill' );
		foreach ( $this->getZoneSlugs() as $zone ) {
			$chips[] = [
				'slug'       => $zone,
				'label'      => $zonesData[ $zone ][ 'label' ],
				'icon_class' => $chipIconClass,
				'severity'   => 'good',
			];
		}
		return $chips;
	}

	private function getZoneSlugs() :array {
		return $this->zoneRenderDataBuilder()->getZoneSlugs();
	}

	private function getZonesData() :array {
		return $this->zoneRenderDataBuilder()->getZonesIndexed();
	}

	/**
	 * @return array{label:string, icon_class:string}
	 */
	private function zoneDataFor( string $zone ) :array {
		$zonesData = $this->getZonesData();
		if ( isset( $zonesData[ $zone ] ) ) {
			return [
				'label'      => $zonesData[ $zone ][ 'label' ],
				'icon_class' => $zonesData[ $zone ][ 'icon_class' ],
			];
		}

		return [
			'label'      => $zone === 'summary'
				? __( 'Summary', 'wp-simple-firewall' )
				: __( 'Scans', 'wp-simple-firewall' ),
			'icon_class' => self::con()->svgs->iconClass( 'grid-1x2-fill' ),
		];
	}

	private function zoneRenderDataBuilder() :ZoneRenderDataBuilder {
		if ( $this->zoneRenderDataBuilder === null ) {
			$this->zoneRenderDataBuilder = new ZoneRenderDataBuilder();
		}
		return $this->zoneRenderDataBuilder;
	}
}
