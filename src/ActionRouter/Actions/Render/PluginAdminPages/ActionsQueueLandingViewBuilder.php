<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueuePayload;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ActionsQueueLandingViewBuilder {

	use PluginControllerConsumer;

	/**
	 * @return array{
	 *   summary:array{
	 *     has_items:bool,
	 *     total_items:int,
	 *     severity:string,
	 *     icon_class:string,
	 *     subtext:string
	 *   },
	 *   zones_indexed:array<string,array{
	 *     slug:string,
	 *     label:string,
	 *     icon_class:string,
	 *     severity:string,
	 *     total_issues:int,
	 *     items:list<array{
	 *       key:string,
	 *       zone:string,
	 *       label:string,
	 *       count:int,
	 *       severity:string,
	 *       description:string,
	 *       href:string,
	 *       action:string
	 *     }>
	 *   }>,
	 *   zone_tiles:list<array{
	 *     key:string,
	 *     panel_target:string,
	 *     is_enabled:bool,
	 *     is_disabled:bool,
	 *     label:string,
	 *     icon_class:string,
	 *     status:string,
	 *     status_label:string,
	 *     total_issues:int,
	 *     critical_count:int,
	 *     warning_count:int,
	 *     summary_text:string,
	 *     items:list<array<string,mixed>>
	 *   }>,
	 *   severity_strip:array{
	 *     severity:string,
	 *     label:string,
	 *     icon_class:string,
	 *     summary_text:string,
	 *     subtext:string,
	 *     total_items:int,
	 *     critical_count:int,
	 *     warning_count:int
	 *   },
	 *   all_clear:array{
	 *     title:string,
	 *     subtitle:string,
	 *     icon_class:string,
	 *     zone_chips:list<array{
	 *       slug:string,
	 *       label:string,
	 *       icon_class:string,
	 *       severity:string
	 *     }>
	 *   }
	 * }
	 */
	public function build( array $needsAttentionPayload ) :array {
		$summary = $this->extractQueueSummary( $needsAttentionPayload );
		$zonesIndexed = $this->buildZonesIndexed( $needsAttentionPayload );
		$zoneTiles = $this->buildZoneTiles( $zonesIndexed );

		return [
			'summary'        => $summary,
			'zones_indexed'  => $zonesIndexed,
			'zone_tiles'     => $zoneTiles,
			'severity_strip' => $this->buildSeverityStripContract( $summary, $zoneTiles ),
			'all_clear'      => $this->buildAllClearContract( $needsAttentionPayload, $zonesIndexed ),
		];
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
	private function extractQueueSummary( array $needsAttentionPayload ) :array {
		return NeedsAttentionQueuePayload::summary(
			$needsAttentionPayload,
			[
				'has_items'   => false,
				'total_items' => 0,
				'severity'    => 'good',
				'icon_class'  => self::con()->svgs->iconClass( 'shield-check' ),
				'subtext'     => '',
			]
		);
	}

	/**
	 * @return array<string,array{
	 *   slug:string,
	 *   label:string,
	 *   icon_class:string,
	 *   severity:string,
	 *   total_issues:int,
	 *   items:list<array{
	 *     key:string,
	 *     zone:string,
	 *     label:string,
	 *     count:int,
	 *     severity:string,
	 *     description:string,
	 *     href:string,
	 *     action:string
	 *   }>
	 * }>
	 */
	private function buildZonesIndexed( array $needsAttentionPayload ) :array {
		$zones = [];
		foreach ( $this->getZoneDefinitions() as $zoneDefinition ) {
			$zones[ $zoneDefinition[ 'slug' ] ] = [
				'slug'         => $zoneDefinition[ 'slug' ],
				'label'        => $zoneDefinition[ 'label' ],
				'icon_class'   => self::con()->svgs->iconClass( $zoneDefinition[ 'icon' ] ),
				'severity'     => 'good',
				'total_issues' => 0,
				'items'        => [],
			];
		}

		foreach ( NeedsAttentionQueuePayload::zoneGroups( $needsAttentionPayload ) as $zoneGroup ) {
			$slug = sanitize_key( (string)( $zoneGroup[ 'slug' ] ?? '' ) );
			if ( !isset( $zones[ $slug ] ) ) {
				continue;
			}

			$zones[ $slug ] = [
				'slug'         => $slug,
				'label'        => (string)( $zoneGroup[ 'label' ] ?? $zones[ $slug ][ 'label' ] ),
				'icon_class'   => (string)( $zoneGroup[ 'icon_class' ] ?? $zones[ $slug ][ 'icon_class' ] ),
				'severity'     => (string)( $zoneGroup[ 'severity' ] ?? 'good' ),
				'total_issues' => (int)( $zoneGroup[ 'total_issues' ] ?? 0 ),
				'items'        => \is_array( $zoneGroup[ 'items' ] ?? null ) ? \array_values( $zoneGroup[ 'items' ] ) : [],
			];
		}

		return $zones;
	}

	/**
	 * @param array<string,array{
	 *   slug:string,
	 *   label:string,
	 *   icon_class:string,
	 *   severity:string,
	 *   total_issues:int,
	 *   items:list<array{
	 *     key:string,
	 *     zone:string,
	 *     label:string,
	 *     count:int,
	 *     severity:string,
	 *     description:string,
	 *     href:string,
	 *     action:string
	 *   }>
	 * }> $zonesIndexed
	 * @return list<array{
	 *   key:string,
	 *   panel_target:string,
	 *   is_enabled:bool,
	 *   is_disabled:bool,
	 *   label:string,
	 *   icon_class:string,
	 *   status:string,
	 *   status_label:string,
	 *   total_issues:int,
	 *   critical_count:int,
	 *   warning_count:int,
	 *   summary_text:string,
	 *   items:list<array<string,mixed>>
	 * }>
	 */
	private function buildZoneTiles( array $zonesIndexed ) :array {
		return \array_map(
			function ( array $zone ) :array {
				$countBySeverity = NeedsAttentionQueuePayload::countsFromItems( $zone[ 'items' ] );
				$totalIssues = $zone[ 'total_issues' ];
				$isEnabled = $totalIssues > 0;

				return [
					'key'            => $zone[ 'slug' ],
					'panel_target'   => $zone[ 'slug' ],
					'is_enabled'     => $isEnabled,
					'is_disabled'    => !$isEnabled,
					'label'          => $zone[ 'label' ],
					'icon_class'     => $zone[ 'icon_class' ],
					'status'         => $zone[ 'severity' ],
					'status_label'   => $this->statusLabel( $zone[ 'severity' ] ),
					'total_issues'   => $totalIssues,
					'critical_count' => $countBySeverity[ 'critical' ],
					'warning_count'  => $countBySeverity[ 'warning' ],
					'summary_text'   => $this->buildZoneSummaryText( $totalIssues, $countBySeverity ),
					'items'          => $zone[ 'items' ],
				];
			},
			\array_values( $zonesIndexed )
		);
	}

	/**
	 * @param list<array{
	 *   key:string,
	 *   panel_target:string,
	 *   is_enabled:bool,
	 *   is_disabled:bool,
	 *   label:string,
	 *   icon_class:string,
	 *   status:string,
	 *   status_label:string,
	 *   total_issues:int,
	 *   critical_count:int,
	 *   warning_count:int,
	 *   summary_text:string,
	 *   items:list<array<string,mixed>>
	 * }> $zoneTiles
	 * @return array{
	 *   severity:string,
	 *   label:string,
	 *   icon_class:string,
	 *   summary_text:string,
	 *   subtext:string,
	 *   total_items:int,
	 *   critical_count:int,
	 *   warning_count:int
	 * }
	 */
	private function buildSeverityStripContract( array $summary, array $zoneTiles ) :array {
		$severityTotals = NeedsAttentionQueuePayload::countsFromZoneGroups( \array_map(
			static fn( array $tile ) :array => [
				'slug'         => $tile[ 'key' ],
				'label'        => $tile[ 'label' ],
				'icon_class'   => $tile[ 'icon_class' ],
				'severity'     => $tile[ 'status' ],
				'total_issues' => $tile[ 'total_issues' ],
				'items'        => \is_array( $tile[ 'items' ] ?? null ) ? $tile[ 'items' ] : [],
			],
			$zoneTiles
		) );
		$criticalCount = $severityTotals[ 'critical' ];
		$warningCount = $severityTotals[ 'warning' ];

		return [
			'severity'       => $summary[ 'severity' ],
			'label'          => $summary[ 'has_items' ]
				? __( 'Action Required', 'wp-simple-firewall' )
				: __( 'All Clear', 'wp-simple-firewall' ),
			'icon_class'     => $summary[ 'icon_class' ],
			'summary_text'   => $summary[ 'has_items' ]
				? sprintf(
					__( '%1$s critical - %2$s warnings - %3$s items total', 'wp-simple-firewall' ),
					$criticalCount,
					$warningCount,
					$summary[ 'total_items' ]
				)
				: __( 'No actions currently require your attention.', 'wp-simple-firewall' ),
			'subtext'        => (string)$summary[ 'subtext' ],
			'total_items'    => (int)$summary[ 'total_items' ],
			'critical_count' => $criticalCount,
			'warning_count'  => $warningCount,
		];
	}

	/**
	 * @param array<string,array{
	 *   slug:string,
	 *   label:string,
	 *   icon_class:string,
	 *   severity:string,
	 *   total_issues:int,
	 *   items:list<array{
	 *     key:string,
	 *     zone:string,
	 *     label:string,
	 *     count:int,
	 *     severity:string,
	 *     description:string,
	 *     href:string,
	 *     action:string
	 *   }>
	 * }> $zonesIndexed
	 * @return array{
	 *   title:string,
	 *   subtitle:string,
	 *   icon_class:string,
	 *   zone_chips:list<array{
	 *     slug:string,
	 *     label:string,
	 *     icon_class:string,
	 *     severity:string
	 *   }>
	 * }
	 */
	private function buildAllClearContract( array $needsAttentionPayload, array $zonesIndexed ) :array {
		$strings = $needsAttentionPayload[ 'render_data' ][ 'strings' ] ?? [];
		$chipIconClass = self::con()->svgs->iconClass( 'check-circle-fill' );

		return [
			'title'      => (string)( $strings[ 'all_clear_title' ] ?? __( 'All security zones are clear', 'wp-simple-firewall' ) ),
			'subtitle'   => (string)( $strings[ 'all_clear_subtitle' ] ?? __( 'Shield is actively protecting your site. Nothing requires your action.', 'wp-simple-firewall' ) ),
			'icon_class' => (string)( $strings[ 'all_clear_icon_class' ] ?? self::con()->svgs->iconClass( 'shield-check' ) ),
			'zone_chips' => \array_map(
				static fn( array $zone ) :array => [
					'slug'       => $zone[ 'slug' ],
					'label'      => $zone[ 'label' ],
					'icon_class' => $chipIconClass,
					'severity'   => 'good',
				],
				\array_values( $zonesIndexed )
			),
		];
	}

	/**
	 * @param array{critical:int,warning:int} $severityCounts
	 */
	private function buildZoneSummaryText( int $totalIssues, array $severityCounts ) :string {
		if ( $totalIssues < 1 ) {
			return __( 'All clear', 'wp-simple-firewall' );
		}
		if ( $severityCounts[ 'critical' ] > 0 ) {
			return sprintf(
				_n( '%1$s issue - %2$s critical', '%1$s issues - %2$s critical', $totalIssues, 'wp-simple-firewall' ),
				$totalIssues,
				$severityCounts[ 'critical' ]
			);
		}
		return sprintf(
			_n( '%1$s issue', '%1$s issues', $totalIssues, 'wp-simple-firewall' ),
			$totalIssues
		);
	}

	private function statusLabel( string $status ) :string {
		switch ( $status ) {
			case 'critical':
				return __( 'Critical', 'wp-simple-firewall' );
			case 'warning':
				return __( 'Warning', 'wp-simple-firewall' );
			default:
				return __( 'Good', 'wp-simple-firewall' );
		}
	}

	/**
	 * @return array<string,array{
	 *   slug:string,
	 *   label:string,
	 *   icon:string
	 * }>
	 */
	private function getZoneDefinitions() :array {
		return PluginNavs::actionsLandingZoneDefinitions();
	}
}
