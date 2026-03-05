<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	BuildMeter,
	Component\Base as MeterComponent,
	Handler,
	Meter\MeterSummary
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\FindSessions;

class PageOperatorModeLanding extends BaseRender {

	public const SLUG = 'plugin_admin_page_operator_mode_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/operator_mode_landing.twig';

	protected function getRenderData() :array {
		$queuePayload = $this->getQueuePayload();
		$queueSummary = $this->getQueueSummary( $queuePayload );
		$queueZoneGroups = $this->getQueueZoneGroups( $queuePayload );

		$configMeter = ( new Handler() )->getMeter( MeterSummary::SLUG, true, MeterComponent::CHANNEL_CONFIG );
		$configPercentage = (int)( $configMeter[ 'totals' ][ 'percentage' ] ?? 0 );
		$configPercentage = max( 0, min( 100, $configPercentage ) );
		$configTraffic = BuildMeter::trafficFromPercentage( $configPercentage );

		$sessionCount = $this->getInvestigateActiveSessionsCount();
		$reportsCount = $this->getGeneratedReportsCount();

		return [
			'vars' => [
				'mode_grid_cells' => [
					$this->buildActionsCell( $queueSummary, $queueZoneGroups ),
					$this->buildInvestigateCell( $sessionCount ),
					$this->buildConfigureCell( $configPercentage, $configTraffic ),
					$this->buildReportsCell( $reportsCount ),
				],
			],
		];
	}

	private function getQueuePayload() :array {
		try {
			$payload = self::con()->action_router->action( NeedsAttentionQueue::class )->payload();
		}
		catch ( \Throwable $e ) {
			$payload = [];
		}
		return $payload;
	}

	/**
	 * @return array{has_items:bool,total_items:int,severity:string,icon_class:string,subtext:string}
	 */
	private function getQueueSummary( array $payload ) :array {
		$defaults = [
			'has_items'   => false,
			'total_items' => 0,
			'severity'    => 'good',
			'icon_class'  => self::con()->svgs->iconClass( 'shield-check' ),
			'subtext'     => '',
		];

		try {
			$summary = NeedsAttentionQueue::summaryFromRenderPayload( $payload );
		}
		catch ( \Throwable $e ) {
			$summary = [];
		}

		$defaults[ 'has_items' ] = (bool)( $summary[ 'has_items' ] ?? false );
		$defaults[ 'total_items' ] = (int)( $summary[ 'total_items' ] ?? 0 );
		$defaults[ 'severity' ] = (string)( $summary[ 'severity' ] ?? 'good' );
		$defaults[ 'icon_class' ] = (string)( $summary[ 'icon_class' ] ?? $defaults[ 'icon_class' ] );
		$defaults[ 'subtext' ] = (string)( $summary[ 'subtext' ] ?? '' );
		return $defaults;
	}

	/**
	 * @return list<array{severity:string,total_issues:int}>
	 */
	private function getQueueZoneGroups( array $payload ) :array {
		$groups = $payload[ 'render_data' ][ 'vars' ][ 'zone_groups' ] ?? [];
		if ( !\is_array( $groups ) ) {
			return [];
		}
		return \array_values( \array_filter( \array_map( function ( $group ) {
			if ( !\is_array( $group ) ) {
				return null;
			}
			return [
				'severity'    => (string)( $group[ 'severity' ] ?? '' ),
				'total_issues' => (int)( $group[ 'total_issues' ] ?? 0 ),
			];
		}, $groups ) ) );
	}

	/**
	 * @param array{has_items:bool,total_items:int,severity:string,icon_class:string,subtext:string} $queueSummary
	 * @param list<array{severity:string,total_issues:int}> $zoneGroups
	 */
	private function buildActionsCell( array $queueSummary, array $zoneGroups ) :array {
		$indicatorText = $queueSummary[ 'has_items' ]
			? sprintf(
				_n( '%s issue needs attention', '%s issues need attention', $queueSummary[ 'total_items' ], 'wp-simple-firewall' ),
				$queueSummary[ 'total_items' ]
			)
			: __( 'All Clear', 'wp-simple-firewall' );

		return [
			'mode'             => PluginNavs::MODE_ACTIONS,
			'label'            => PluginNavs::modeLabel( PluginNavs::MODE_ACTIONS ),
			'description'      => __( 'Resolve active findings and maintenance issues.', 'wp-simple-firewall' ),
			'href'             => $this->modeHref( PluginNavs::MODE_ACTIONS ),
			'icon_class'       => $queueSummary[ 'icon_class' ],
			'indicator_type'   => 'status',
			'indicator_class'  => 'status-'.$queueSummary[ 'severity' ],
			'indicator_text'   => $indicatorText,
			'indicator_subtext'=> $queueSummary[ 'has_items' ] ? $this->buildQueueBreakdownText( $zoneGroups ) : '',
			'footnote'         => $queueSummary[ 'subtext' ],
		];
	}

	/**
	 * @param list<array{severity:string,total_issues:int}> $zoneGroups
	 */
	private function buildQueueBreakdownText( array $zoneGroups ) :string {
		$critical = 0;
		$warning = 0;
		foreach ( $zoneGroups as $group ) {
			if ( $group[ 'severity' ] === 'critical' ) {
				$critical += max( 0, (int)$group[ 'total_issues' ] );
			}
			elseif ( $group[ 'severity' ] === 'warning' ) {
				$warning += max( 0, (int)$group[ 'total_issues' ] );
			}
		}

		$parts = [];
		if ( $critical > 0 ) {
			$parts[] = sprintf( _n( '%s critical', '%s critical', $critical, 'wp-simple-firewall' ), $critical );
		}
		if ( $warning > 0 ) {
			$parts[] = sprintf( _n( '%s warning', '%s warnings', $warning, 'wp-simple-firewall' ), $warning );
		}

		return empty( $parts ) ? '' : implode( ' - ', $parts );
	}

	private function buildInvestigateCell( int $sessionCount ) :array {
		$text = $sessionCount > 0
			? sprintf( _n( '%s active session', '%s active sessions', $sessionCount, 'wp-simple-firewall' ), $sessionCount )
			: __( 'Activity & Events', 'wp-simple-firewall' );

		return [
			'mode'            => PluginNavs::MODE_INVESTIGATE,
			'label'           => PluginNavs::modeLabel( PluginNavs::MODE_INVESTIGATE ),
			'description'     => __( 'Investigate activity, traffic, and IP behavior.', 'wp-simple-firewall' ),
			'href'            => $this->modeHref( PluginNavs::MODE_INVESTIGATE ),
			'icon_class'      => self::con()->svgs->iconClass( 'search' ),
			'indicator_type'  => 'status',
			'indicator_class' => 'status-neutral',
			'indicator_text'  => $text,
		];
	}

	private function buildConfigureCell( int $percentage, string $status ) :array {
		return [
			'mode'              => PluginNavs::MODE_CONFIGURE,
			'label'             => PluginNavs::modeLabel( PluginNavs::MODE_CONFIGURE ),
			'description'       => __( 'Tune security zones, rules, and tools.', 'wp-simple-firewall' ),
			'href'              => $this->modeHref( PluginNavs::MODE_CONFIGURE ),
			'icon_class'        => self::con()->svgs->iconClass( 'sliders' ),
			'indicator_type'    => 'posture',
			'posture_percentage'=> $percentage,
			'posture_status'    => $status,
			'posture_text'      => sprintf( __( '%s%% configured', 'wp-simple-firewall' ), $percentage ),
		];
	}

	private function buildReportsCell( int $reportsCount ) :array {
		$text = $reportsCount > 0
			? sprintf( _n( '%s report', '%s reports', $reportsCount, 'wp-simple-firewall' ), $reportsCount )
			: __( 'Summaries & Alerts', 'wp-simple-firewall' );

		return [
			'mode'            => PluginNavs::MODE_REPORTS,
			'label'           => PluginNavs::modeLabel( PluginNavs::MODE_REPORTS ),
			'description'     => __( 'Review security reports and trends.', 'wp-simple-firewall' ),
			'href'            => $this->modeHref( PluginNavs::MODE_REPORTS ),
			'icon_class'      => self::con()->svgs->iconClass( 'bar-chart-line' ),
			'indicator_type'  => 'status',
			'indicator_class' => 'status-neutral',
			'indicator_text'  => $text,
		];
	}

	private function getInvestigateActiveSessionsCount() :int {
		try {
			$count = \count( ( new FindSessions() )->mostRecent( 5 ) );
		}
		catch ( \Exception $e ) {
			$count = 0;
		}
		return \max( 0, $count );
	}

	private function getGeneratedReportsCount() :int {
		try {
			$count = (int)self::con()->db_con->reports->getQuerySelector()
							 ->addWhere( 'unique_id', '', '!=' )
							 ->count();
		}
		catch ( \Exception $e ) {
			$count = 0;
		}
		return \max( 0, $count );
	}

	private function modeHref( string $mode ) :string {
		$entry = PluginNavs::defaultEntryForMode( $mode );
		return self::con()->plugin_urls->adminTopNav( $entry[ 'nav' ], $entry[ 'subnav' ] );
	}
}
