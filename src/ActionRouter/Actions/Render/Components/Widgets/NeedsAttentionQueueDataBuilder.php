<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Zones\ZoneRenderDataBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;
use FernleafSystems\Wordpress\Services\Services;

class NeedsAttentionQueueDataBuilder {

	use PluginControllerConsumer;

	private ?ZoneRenderDataBuilder $zoneRenderDataBuilder = null;

	/**
	 * @param array{compact_all_clear?:bool} $actionData
	 * @return array{
	 *   flags:array{has_items:bool,compact_all_clear:bool},
	 *   strings:array{
	 *     status_strip_icon_class:string,
	 *     status_strip_text:string,
	 *     status_strip_subtext:string,
	 *     title:string,
	 *     issues_found:string,
	 *     all_clear:string,
	 *     all_clear_icon_class:string,
	 *     all_clear_title:string,
	 *     all_clear_subtitle:string,
	 *     all_clear_message:string,
	 *     last_scan_subtext:string
	 *   },
	 *   vars:array{
	 *     summary:array{
	 *       has_items:bool,
	 *       total_items:int,
	 *       severity:string,
	 *       icon_class:string,
	 *       subtext:string
	 *     },
	 *     overall_severity:string,
	 *     total_items:int,
	 *     zone_groups:list<array<string,mixed>>,
	 *     zone_chips:list<array<string,mixed>>
	 *   }
	 * }
	 */
	public function build( array $actionData = [] ) :array {
		$baseData = $this->buildBaseData();
		$isCompactAllClear = !empty( $actionData[ 'compact_all_clear' ] );
		$statusStripText = $baseData[ 'summary' ][ 'has_items' ]
			? sprintf(
				_n( '%s issue needs your attention', '%s issues need your attention', $baseData[ 'summary' ][ 'total_items' ], 'wp-simple-firewall' ),
				$baseData[ 'summary' ][ 'total_items' ]
			)
			: __( 'Your site is secure', 'wp-simple-firewall' );

		return [
			'flags'   => [
				'has_items'         => $baseData[ 'summary' ][ 'has_items' ],
				'compact_all_clear' => $isCompactAllClear,
			],
			'strings' => [
				'status_strip_icon_class' => $baseData[ 'summary' ][ 'icon_class' ],
				'status_strip_text'       => $statusStripText,
				'status_strip_subtext'    => $baseData[ 'summary' ][ 'subtext' ],
				'title'                   => __( 'Action Required', 'wp-simple-firewall' ),
				'issues_found'            => __( 'Actions Required', 'wp-simple-firewall' ),
				'all_clear'               => __( 'All Clear', 'wp-simple-firewall' ),
				'all_clear_icon_class'    => self::con()->svgs->iconClass( 'shield-check' ),
				'all_clear_title'         => __( 'All security zones are clear', 'wp-simple-firewall' ),
				'all_clear_subtitle'      => __( 'Shield is actively protecting your site. Nothing requires your action.', 'wp-simple-firewall' ),
				'all_clear_message'       => __( 'No security actions currently require your attention.', 'wp-simple-firewall' ),
				'last_scan_subtext'       => $baseData[ 'last_scan_subtext' ],
			],
			'vars'    => [
				'summary'          => $baseData[ 'summary' ],
				'overall_severity' => $baseData[ 'summary' ][ 'severity' ],
				'total_items'      => $baseData[ 'summary' ][ 'total_items' ],
				'zone_groups'      => $baseData[ 'zone_groups' ],
				'zone_chips'       => $this->buildAllClearZoneChips(),
			],
		];
	}

	/**
	 * @return array{
	 *   summary:array{
	 *     has_items:bool,
	 *     total_items:int,
	 *     severity:string,
	 *     icon_class:string,
	 *     subtext:string
	 *   },
	 *   zone_groups:list<array<string,mixed>>,
	 *   last_scan_subtext:string
	 * }
	 */
	private function buildBaseData() :array {
		$provider = new AttentionItemsProvider();
		$items = $provider->buildQueueItems();
		$totalItems = (int)\array_sum( \array_column( $items, 'count' ) );
		$latestScanAt = $provider->getLatestCompletedScanTimestamp( self::con()->comps->scans->getScanSlugs() );
		$lastScanSubtext = $latestScanAt > 0
			? sprintf(
				__( 'Last scan: %s', 'wp-simple-firewall' ),
				Services::Request()->carbon( true )->setTimestamp( $latestScanAt )->diffForHumans()
			)
			: '';

		return [
			'summary'           => $this->buildQueueSummaryContract(
				!empty( $items ),
				$totalItems,
				$items,
				$lastScanSubtext
			),
			'zone_groups'       => \array_values( $this->buildZoneGroups( $items ) ),
			'last_scan_subtext' => $lastScanSubtext,
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

		$actionsZones = PluginNavs::actionsLandingZoneDefinitions();
		if ( isset( $actionsZones[ $zone ] ) ) {
			return [
				'label'      => $actionsZones[ $zone ][ 'label' ],
				'icon_class' => self::con()->svgs->iconClass( $actionsZones[ $zone ][ 'icon' ] ),
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
