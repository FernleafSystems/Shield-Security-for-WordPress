<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\OperatorModeSwitch;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	BuildMeter,
	Component\Base as MeterComponent,
	Handler,
	Meter\MeterSummary
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\OperatorModePreference;

class PageOperatorModeLanding extends BaseRender {

	public const SLUG = 'plugin_admin_page_operator_mode_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/operator_mode_landing.twig';

	protected function getRenderData() :array {
		$con = self::con();

		$queuePayload = $con->action_router->action( NeedsAttentionQueue::class )->payload();
		$configMeter = ( new Handler() )->getMeter( MeterSummary::SLUG, true, MeterComponent::CHANNEL_CONFIG );
		$configPercentage = (int)( $configMeter[ 'totals' ][ 'percentage' ] ?? 0 );
		$configTraffic = BuildMeter::trafficFromPercentage( $configPercentage );
		$defaultMode = ( new OperatorModePreference() )->getCurrent();

		return [
			'hrefs'   => [
				'actions_queue'       => $this->modeHref( PluginNavs::MODE_ACTIONS ),
				'operator_mode_switch' => $con->plugin_urls->noncedPluginAction(
					OperatorModeSwitch::class,
					$con->plugin_urls->adminTopNav( PluginNavs::NAV_DASHBOARD, PluginNavs::SUBNAV_DASHBOARD_OVERVIEW )
				),
			],
			'strings' => [
				'actions_queue_title' => PluginNavs::modeLabel( PluginNavs::MODE_ACTIONS ),
				'set_default_mode'    => __( 'Always start in', 'wp-simple-firewall' ),
				'save_default_mode'   => __( 'Save Default', 'wp-simple-firewall' ),
				'start_mode_help'     => __( 'Choose where Shield opens when you select the plugin menu.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'actions_hero' => $this->buildActionsHero( $queuePayload ),
				'mode_strip'   => $this->buildModeStrip( $configPercentage, $configTraffic ),
				'mode_options' => $this->buildModeOptions(),
				'default_mode' => $defaultMode,
			],
		];
	}

	private function buildActionsHero( array $queuePayload ) :array {
		$hasItems = !empty( $queuePayload[ 'flags' ][ 'has_items' ] );
		$totalItems = (int)( $queuePayload[ 'vars' ][ 'total_items' ] ?? 0 );
		$severity = $hasItems
			? $this->normalizeSeverity( (string)( $queuePayload[ 'vars' ][ 'overall_severity' ] ?? 'critical' ) )
			: 'good';

		return [
			'severity'     => $severity,
			'badge_status' => $severity,
			'icon_class'   => (string)( $queuePayload[ 'strings' ][ $hasItems ? 'status_strip_icon_class' : 'all_clear_icon_class' ]
				?? self::con()->svgs->iconClass( $hasItems ? 'exclamation-triangle-fill' : 'shield-check' ) ),
			'subtitle'     => $hasItems
				? sprintf(
					_n(
						'%s issue needs your attention - review and resolve now',
						'%s issues need your attention - review and resolve now',
						$totalItems,
						'wp-simple-firewall'
					),
					$totalItems
				)
				: __( 'All clear - no issues require your attention', 'wp-simple-firewall' ),
			'meta'         => (string)( $queuePayload[ 'strings' ][ 'status_strip_subtext' ] ?? '' ),
			'badge_text'   => $hasItems
				? sprintf( _n( '%s item', '%s items', $totalItems, 'wp-simple-firewall' ), $totalItems )
				: __( 'All clear', 'wp-simple-firewall' ),
		];
	}

	private function buildModeStrip( int $configPercentage, string $configTraffic ) :array {
		return [
			[
				'mode'       => PluginNavs::MODE_INVESTIGATE,
				'label'      => PluginNavs::modeLabel( PluginNavs::MODE_INVESTIGATE ),
				'href'       => $this->modeHref( PluginNavs::MODE_INVESTIGATE ),
				'status'     => 'info',
				'badge_text' => '',
				'icon_class' => self::con()->svgs->iconClass( 'search' ),
				'summary'    => $this->modeSummary( PluginNavs::MODE_INVESTIGATE ),
			],
			[
				'mode'         => PluginNavs::MODE_CONFIGURE,
				'label'        => PluginNavs::modeLabel( PluginNavs::MODE_CONFIGURE ),
				'href'         => $this->modeHref( PluginNavs::MODE_CONFIGURE ),
				'status'       => $configTraffic,
				'badge_text'   => sprintf( '%s%%', $configPercentage ),
				'badge_status' => $configTraffic,
				'icon_class'   => self::con()->svgs->iconClass( 'gear' ),
				'summary'      => $this->configureSummary( $configTraffic ),
			],
			[
				'mode'       => PluginNavs::MODE_REPORTS,
				'label'      => PluginNavs::modeLabel( PluginNavs::MODE_REPORTS ),
				'href'       => $this->modeHref( PluginNavs::MODE_REPORTS ),
				'status'     => 'warning',
				'badge_text' => '',
				'icon_class' => self::con()->svgs->iconClass( 'file-text-fill' ),
				'summary'    => $this->modeSummary( PluginNavs::MODE_REPORTS ),
			],
		];
	}

	private function modeHref( string $mode ) :string {
		$entry = PluginNavs::defaultEntryForMode( $mode );
		return self::con()->plugin_urls->adminTopNav( $entry[ 'nav' ], $entry[ 'subnav' ] );
	}

	private function buildModeOptions() :array {
		$options = [
			[
				'mode'  => '',
				'label' => PluginNavs::modeLabel( '' ),
			]
		];
		foreach ( PluginNavs::allOperatorModes() as $mode ) {
			$options[] = [
				'mode'  => $mode,
				'label' => PluginNavs::modeLabel( $mode ),
			];
		}
		return $options;
	}

	private function modeSummary( string $mode ) :string {
		switch ( $mode ) {
			case PluginNavs::MODE_ACTIONS:
				$summary = __( 'Resolve active findings and maintenance issues.', 'wp-simple-firewall' );
				break;
			case PluginNavs::MODE_INVESTIGATE:
				$summary = __( 'Investigate activity, traffic, and IP behavior.', 'wp-simple-firewall' );
				break;
			case PluginNavs::MODE_CONFIGURE:
				$summary = __( 'Tune security zones, rules, and tools.', 'wp-simple-firewall' );
				break;
			case PluginNavs::MODE_REPORTS:
				$summary = __( 'Review security reports and trends.', 'wp-simple-firewall' );
				break;
			default:
				$summary = '';
				break;
		}
		return $summary;
	}

	private function normalizeSeverity( string $severity ) :string {
		$severity = \strtolower( \trim( $severity ) );
		return \in_array( $severity, [ 'good', 'warning', 'critical', 'info' ], true ) ? $severity : 'good';
	}

	private function configureSummary( string $configTraffic ) :string {
		switch ( $configTraffic ) {
			case 'critical':
				$summary = __( 'Configuration posture needs immediate review.', 'wp-simple-firewall' );
				break;
			case 'warning':
				$summary = __( 'Configuration posture needs work in a few areas.', 'wp-simple-firewall' );
				break;
			case 'good':
			default:
				$summary = __( 'Configuration posture is strong and stable.', 'wp-simple-firewall' );
				break;
		}
		return $summary;
	}
}
