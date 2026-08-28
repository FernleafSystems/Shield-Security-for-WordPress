<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\ActionsQueueCardDataBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\DashboardLiveMonitorPreference;

/**
 * @phpstan-import-type ActionsQueueCardData from ActionsQueueCardDataBuilder
 * @phpstan-type OperatorModeDestinationCard array{
 *   mode:string,
 *   sidebar_label:string,
 *   href:string,
 *   icon_class:string,
 *   accent:string,
 *   title:string,
 *   description:string,
 *   cta:string,
 *   accessible_label:string
 * }
 */
class PageOperatorModeLanding extends BaseRender {

	public const SLUG = 'plugin_admin_page_operator_mode_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/operator_mode_landing.twig';

	private ?array $attentionQueryCache = null;

	protected function getRenderData() :array {
		$queueCard = $this->buildActionsQueueCardData();

		return [
			'vars' => [
				'dashboard_strip'   => $queueCard[ 'dashboard_strip' ],
				'destination_cards' => $this->buildDestinationCards(),
				'live_monitor'      => $this->buildLiveMonitorVars(),
			],
		];
	}

	/**
	 * @return ActionsQueueCardData
	 */
	protected function buildActionsQueueCardData() :array {
		return ( new ActionsQueueCardDataBuilder() )->build( $this->getAttentionQuery() );
	}

	/**
	 * @return list<OperatorModeDestinationCard>
	 */
	private function buildDestinationCards() :array {
		return [
			$this->buildDestinationCard(
				PluginNavs::MODE_INVESTIGATE,
				'search',
				__( 'Investigate Site', 'wp-simple-firewall' ),
				__( 'Deep dive to explore every aspect of your site including users, plugins, themes & IP addresses.', 'wp-simple-firewall' ),
				__( 'Open Investigation', 'wp-simple-firewall' )
			),
			$this->buildDestinationCard(
				PluginNavs::MODE_CONFIGURE,
				'sliders',
				__( 'Configure', 'wp-simple-firewall' ),
				__( 'Fine tune your WordPress security coverage to exactly what you need.', 'wp-simple-firewall' ),
				__( 'Open Configure', 'wp-simple-firewall' )
			),
			$this->buildDestinationCard(
				PluginNavs::MODE_REPORTS,
				'bar-chart-line',
				__( 'Reports', 'wp-simple-firewall' ),
				__( 'Review security reports and trends.', 'wp-simple-firewall' ),
				__( 'Open Reports', 'wp-simple-firewall' )
			),
		];
	}

	/**
	 * @return OperatorModeDestinationCard
	 */
	private function buildDestinationCard(
		string $mode,
		string $icon,
		string $title,
		string $description,
		string $cta
	) :array {
		return [
			'mode'             => $mode,
			'sidebar_label'    => PluginNavs::modeLabel( $mode ),
			'href'             => $this->modeHref( $mode ),
			'icon_class'       => self::con()->svgs->iconClass( $icon ),
			'accent'           => $mode,
			'title'            => $title,
			'description'      => $description,
			'cta'              => $cta,
			'accessible_label' => $title.'. '.$description.' '.$cta,
		];
	}

	private function buildLiveMonitorVars() :array {
		try {
			$isCollapsed = ( new DashboardLiveMonitorPreference() )->isCollapsed();
		}
		catch ( \Throwable $e ) {
			$isCollapsed = false;
		}

		return [
			'is_collapsed' => $isCollapsed,
			'title'        => __( 'Live Monitor', 'wp-simple-firewall' ),
			'activity'     => __( 'WP Activity', 'wp-simple-firewall' ),
			'traffic'      => __( 'Live Traffic', 'wp-simple-firewall' ),
			'loading'      => __( 'Waiting for live updates...', 'wp-simple-firewall' ),
			'ready'        => __( 'Live monitor updated.', 'wp-simple-firewall' ),
			'error'        => __( 'Live monitor update failed.', 'wp-simple-firewall' ),
		];
	}

	private function modeHref( string $mode ) :string {
		$entry = PluginNavs::defaultEntryForMode( $mode );
		return self::con()->plugin_urls->adminTopNav( $entry[ 'nav' ], $entry[ 'subnav' ] );
	}

	private function getAttentionQuery() :array {
		if ( $this->attentionQueryCache === null ) {
			$this->attentionQueryCache = $this->buildAttentionQuery();
		}

		return $this->attentionQueryCache;
	}

	protected function buildAttentionQuery() :array {
		return self::con()->comps->site_query->attention();
	}
}
