<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\ScanResultsLagWarning;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @phpstan-import-type AttentionGroup from BuildAttentionItems
 * @phpstan-import-type AttentionGroups from BuildAttentionItems
 * @phpstan-import-type AttentionItem from BuildAttentionItems
 * @phpstan-import-type AttentionQuery from BuildAttentionItems
 * @phpstan-type ActionsQueueCardRow array{
 *   key:string,
 *   label:string,
 *   icon_class:string,
 *   severity:string,
 *   count:int
 * }
 * @phpstan-type ActionsQueueCardLane array{
 *   mode:string,
 *   label:string,
 *   description:string,
 *   href:string,
 *   icon_class:string,
 *   edge_status:string,
 *   extra_classes:string,
 *   indicator_type:string,
 *   indicator_severity:string,
 *   indicator_text:string,
 *   indicator_subtext:string
 * }
 * @phpstan-type ActionsQueueCardData array{
 *   summary:array{has_items:bool,total_items:int,severity:string},
 *   subtitle:string,
 *   shield_status:string,
 *   shield_icon_class:string,
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
 *   },
 *   actions_lane:ActionsQueueCardLane,
 *   actions_queue_rows:list<ActionsQueueCardRow>
 * }
 */
class ActionsQueueCardDataBuilder {

	use PluginControllerConsumer;

	private const VALID_SEVERITIES = [
		'good',
		'warning',
		'critical',
	];

	/**
	 * @param AttentionQuery $attentionQuery
	 * @return ActionsQueueCardData
	 */
	public function build( array $attentionQuery ) :array {
		$attentionQuery = ( new DashboardAttentionQueryFilter() )->filter( $attentionQuery );
		$queueSummary = $this->getQueueSummary( $attentionQuery );
		$zoneGroups = $this->getQueueZoneGroups( $attentionQuery );
		$shieldStatus = $this->normalizeSeverity( $queueSummary[ 'severity' ] );

		return [
			'summary'            => $queueSummary,
			'subtitle'           => $this->buildShieldSubtitle( $queueSummary ),
			'shield_status'      => $shieldStatus,
			'shield_icon_class'  => $this->buildShieldIconClass( $shieldStatus ),
			'all_clear'          => $this->buildAllClearData(),
			'actions_lane'       => $this->buildActionsLane( $queueSummary, $zoneGroups ),
			'actions_queue_rows' => $this->buildActionsQueueRows( $zoneGroups ),
		];
	}

	/**
	 * @param AttentionQuery $attentionQuery
	 * @return array{has_items:bool,total_items:int,severity:string}
	 */
	private function getQueueSummary( array $attentionQuery ) :array {
		$hasItems = !$attentionQuery[ 'summary' ][ 'is_all_clear' ];

		return [
			'has_items'   => $hasItems,
			'total_items' => $attentionQuery[ 'summary' ][ 'total' ],
			'severity'    => $attentionQuery[ 'summary' ][ 'severity' ],
		];
	}

	/**
	 * @param AttentionQuery $attentionQuery
	 * @return AttentionGroups
	 */
	private function getQueueZoneGroups( array $attentionQuery ) :array {
		return $attentionQuery[ 'groups' ];
	}

	private function normalizeSeverity( string $severity ) :string {
		$severity = sanitize_key( $severity );
		return \in_array( $severity, self::VALID_SEVERITIES, true ) ? $severity : 'good';
	}

	/**
	 * @param array{has_items:bool,total_items:int,severity:string} $queueSummary
	 */
	private function buildShieldSubtitle( array $queueSummary ) :string {
		$warning = ( new ScanResultsLagWarning() )->getText();
		if ( $warning !== '' ) {
			return $warning;
		}

		return $queueSummary[ 'has_items' ]
			? sprintf(
				_n( '%s issue needs your attention.', '%s issues need your attention.', $queueSummary[ 'total_items' ], 'wp-simple-firewall' ),
				$queueSummary[ 'total_items' ]
			)
			: __( 'Your site is protected. All systems operational.', 'wp-simple-firewall' );
	}

	private function buildShieldIconClass( string $shieldStatus ) :string {
		$iconMap = [
			'good'     => 'shield-shaded',
			'warning'  => 'shield-exclamation',
			'critical' => 'shield-x',
		];

		return self::con()->svgs->iconClass( $iconMap[ $shieldStatus ] );
	}

	/**
	 * @param array{has_items:bool,total_items:int,severity:string} $queueSummary
	 * @param AttentionGroups $zoneGroups
	 * @return ActionsQueueCardLane
	 */
	private function buildActionsLane( array $queueSummary, array $zoneGroups ) :array {
		$severity = $this->normalizeSeverity( $queueSummary[ 'severity' ] );
		$iconMap = [
			'good'     => 'shield-check',
			'warning'  => 'shield-exclamation',
			'critical' => 'shield-x',
		];
		$indicatorText = $queueSummary[ 'has_items' ]
			? sprintf(
				_n( '%s issue needs attention', '%s issues need attention', $queueSummary[ 'total_items' ], 'wp-simple-firewall' ),
				$queueSummary[ 'total_items' ]
			)
			: __( 'All Clear', 'wp-simple-firewall' );

		$extraClasses = '';
		if ( $severity === 'critical' ) {
			$extraClasses = ' has-critical';
		}
		elseif ( $severity === 'warning' ) {
			$extraClasses = ' has-issues';
		}

		$entry = PluginNavs::defaultEntryForMode( PluginNavs::MODE_ACTIONS );

		return [
			'mode'               => PluginNavs::MODE_ACTIONS,
			'label'              => PluginNavs::modeLabel( PluginNavs::MODE_ACTIONS ),
			'description'        => __( 'Take action on critical issues such as scan results, vulnerabilities and malware.', 'wp-simple-firewall' ),
			'href'               => self::con()->plugin_urls->adminTopNav( $entry[ 'nav' ], $entry[ 'subnav' ] ),
			'icon_class'         => self::con()->svgs->iconClass( $iconMap[ $severity ] ),
			'edge_status'        => 'shield',
			'extra_classes'      => $extraClasses,
			'indicator_type'     => 'status',
			'indicator_severity' => $severity,
			'indicator_text'     => $indicatorText,
			'indicator_subtext'  => $queueSummary[ 'has_items' ] ? $this->buildQueueBreakdownText( $zoneGroups ) : '',
		];
	}

	/**
	 * @param AttentionGroups $zoneGroups
	 * @return list<ActionsQueueCardRow>
	 */
	private function buildActionsQueueRows( array $zoneGroups ) :array {
		$rows = [];

		foreach ( $zoneGroups[ 'scans' ][ 'items' ] as $item ) {
			if ( $item[ 'count' ] < 1 ) {
				continue;
			}
			$rows[] = $this->buildScanQueueRowFromAttentionItem( $item );
		}

		if ( $zoneGroups[ 'maintenance' ][ 'total' ] > 0 ) {
			$rows[] = $this->buildMaintenanceQueueRow( $zoneGroups[ 'maintenance' ] );
		}

		return $rows;
	}

	/**
	 * @param AttentionItem $item
	 * @return ActionsQueueCardRow
	 */
	private function buildScanQueueRowFromAttentionItem( array $item ) :array {
		$key = (string)$item[ 'key' ];

		return [
			'key'        => $key,
			'label'      => $this->dashboardScanQueueRowLabel( $key, (string)$item[ 'label' ] ),
			'icon_class' => self::con()->svgs->iconClass( PluginNavs::actionsLandingScanRowIcon( $key ) ),
			'severity'   => $this->normalizeSeverity( $item[ 'severity' ] ),
			'count'      => $item[ 'count' ],
		];
	}

	/**
	 * @param AttentionGroup $maintenanceGroup
	 * @return ActionsQueueCardRow
	 */
	private function buildMaintenanceQueueRow( array $maintenanceGroup ) :array {
		$count = $maintenanceGroup[ 'total' ];

		return [
			'key'        => 'maintenance',
			'label'      => __( 'Maintenance Items', 'wp-simple-firewall' ),
			'icon_class' => self::con()->svgs->iconClass( 'wrench' ),
			'severity'   => $count > 0 ? 'warning' : 'good',
			'count'      => $count,
		];
	}

	/**
	 * @param AttentionGroups $zoneGroups
	 */
	private function buildQueueBreakdownText( array $zoneGroups ) :string {
		$counts = [
			'critical' => 0,
			'warning'  => 0,
		];
		foreach ( $zoneGroups as $zoneGroup ) {
			foreach ( $zoneGroup[ 'items' ] as $item ) {
				$severity = $this->normalizeSeverity( $item[ 'severity' ] );
				if ( $severity === 'critical' || $severity === 'warning' ) {
					$counts[ $severity ] += $item[ 'count' ];
				}
			}
		}

		$parts = [];
		if ( $counts[ 'critical' ] > 0 ) {
			$parts[] = sprintf( _n( '%s critical', '%s critical', $counts[ 'critical' ], 'wp-simple-firewall' ), $counts[ 'critical' ] );
		}
		if ( $counts[ 'warning' ] > 0 ) {
			$parts[] = sprintf( _n( '%s warning', '%s warnings', $counts[ 'warning' ], 'wp-simple-firewall' ), $counts[ 'warning' ] );
		}

		return empty( $parts ) ? '' : implode( ' - ', $parts );
	}

	private function dashboardScanQueueRowLabel( string $key, string $label ) :string {
		if ( $key === 'plugin_files' ) {
			return __( 'Plugins with Modified Files', 'wp-simple-firewall' );
		}
		if ( $key === 'theme_files' ) {
			return __( 'Themes with Modified Files', 'wp-simple-firewall' );
		}

		return $label;
	}

	/**
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
	private function buildAllClearData() :array {
		$zonesIndexed = \array_map(
			static fn( string $slug, array $zone ) :array => [
				'slug'  => $slug,
				'label' => $zone[ 'label' ],
			],
			\array_keys( PluginNavs::actionsLandingZoneDefinitions() ),
			\array_values( PluginNavs::actionsLandingZoneDefinitions() )
		);

		return ( new ActionsQueueAllClearDataBuilder() )->build( $zonesIndexed );
	}

}
