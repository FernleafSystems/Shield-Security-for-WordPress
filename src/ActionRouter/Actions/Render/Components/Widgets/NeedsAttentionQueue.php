<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Zones\ZoneRenderDataBuilder;
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

		return [
			'flags'   => [
				'has_items' => $hasItems,
			],
			'strings' => [
				'status_strip_icon_class' => $hasItems
					? self::con()->svgs->iconClass( 'exclamation-triangle-fill' )
					: self::con()->svgs->iconClass( 'shield-check' ),
				'status_strip_text'       => $statusStripText,
				'status_strip_subtext'    => $lastScanSubtext,
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
				'overall_severity' => $this->determineOverallSeverity( $items ),
				'total_items'      => $totalItems,
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
			$groups[ $zone ][ 'severity' ] = $this->maxSeverity( [
				$groups[ $zone ][ 'severity' ],
				$item[ 'severity' ],
			] );
		}

		return $groups;
	}

	private function determineOverallSeverity( array $items ) :string {
		if ( empty( $items ) ) {
			return 'good';
		}
		return $this->maxSeverity( \array_column( $items, 'severity' ) );
	}

	private function maxSeverity( array $severities ) :string {
		$rankMap = [
			'good'     => 0,
			'warning'  => 1,
			'critical' => 2,
		];
		$highest = 'good';
		foreach ( $severities as $severity ) {
			if ( ( $rankMap[ $severity ] ?? -1 ) > ( $rankMap[ $highest ] ?? -1 ) ) {
				$highest = $severity;
			}
		}
		return $highest;
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
