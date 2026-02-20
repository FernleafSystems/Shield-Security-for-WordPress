<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\OperatorModeSwitch;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Placeholders\PlaceholderMeter;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Meter\MeterSummary;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\OperatorModePreference;

class PageOperatorModeLanding extends BaseRender {

	public const SLUG = 'plugin_admin_page_operator_mode_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/operator_mode_landing.twig';

	protected function getRenderData() :array {
		$con = self::con();

		$queuePayload = $con->action_router->action( NeedsAttentionQueue::class )->payload();
		$defaultMode = ( new OperatorModePreference() )->getCurrent();

		return [
			'content' => [
				'needs_attention_queue' => (string)( $queuePayload[ 'html' ] ?? '' ),
				'configure_meter'       => $con->action_router->render( PlaceholderMeter::class, [
					'meter_slug'    => MeterSummary::SLUG,
					'meter_channel' => 'config',
					'is_hero'       => true,
				] ),
			],
			'hrefs'   => [
				'configure_score_details' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_DASHBOARD, PluginNavs::SUBNAV_DASHBOARD_GRADES ),
				'operator_mode_switch'    => $con->plugin_urls->noncedPluginAction(
					OperatorModeSwitch::class,
					$con->plugin_urls->adminTopNav( PluginNavs::NAV_DASHBOARD, PluginNavs::SUBNAV_DASHBOARD_OVERVIEW )
				),
			],
			'strings' => [
				'actions_queue_title'   => __( 'Actions Queue', 'wp-simple-firewall' ),
				'configure_score_title' => __( 'Configuration Posture Score', 'wp-simple-firewall' ),
				'set_default_mode'      => __( 'Always start in', 'wp-simple-firewall' ),
				'save_default_mode'     => __( 'Save Default', 'wp-simple-firewall' ),
				'view_score_details'    => __( 'View All Security Grades', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'mode_links'   => $this->buildModeLinks(),
				'mode_options' => $this->buildModeOptions(),
				'default_mode' => $defaultMode,
			],
		];
	}

	private function buildModeLinks() :array {
		return \array_map( function ( string $mode ) :array {
			$entry = PluginNavs::defaultEntryForMode( $mode );
			return [
				'mode'    => $mode,
				'label'   => PluginNavs::modeLabel( $mode ),
				'summary' => $this->modeSummary( $mode ),
				'href'    => self::con()->plugin_urls->adminTopNav( $entry[ 'nav' ], $entry[ 'subnav' ] ),
			];
		}, PluginNavs::allOperatorModes() );
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
}
