<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Utilities\URL;

/**
 * The tabs across the top of Investigate: the lookup, the three logs, and live traffic. The same tabs head
 * the log pages, so Investigate reads as one place however it was reached.
 *
 * @phpstan-type InvestigateTab array{key:string,label:string,icon_class:string,href:string,is_current:bool}
 * @phpstan-type InvestigateTabsData array{
 *   tabs:list<InvestigateTab>,
 *   ip_rules:array{label:string,href:string},
 *   label:string
 * }
 */
class InvestigateTabs {

	use PluginControllerConsumer;

	public const LOOKUP = 'lookup';
	public const ACTIVITY = 'activity';
	public const REQUESTS = 'requests';
	public const SESSIONS = 'sessions';
	public const LIVE = 'live';

	/**
	 * @return InvestigateTabsData
	 */
	public function build( string $current ) :array {
		$urls = self::con()->plugin_urls;
		$tabs = [
			[
				'key'        => self::LOOKUP,
				'label'      => __( 'Lookup', 'wp-simple-firewall' ),
				'icon_class' => 'bi bi-search',
				'href'       => $urls->investigateHome(),
			],
			[
				'key'        => self::ACTIVITY,
				'label'      => __( 'Activity log', 'wp-simple-firewall' ),
				'icon_class' => 'bi bi-person-lines-fill',
				'href'       => $urls->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS ),
			],
			[
				'key'        => self::REQUESTS,
				'label'      => __( 'Request log', 'wp-simple-firewall' ),
				'icon_class' => 'bi bi-globe',
				'href'       => $urls->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS ),
			],
			[
				'key'        => self::SESSIONS,
				'label'      => __( 'Sessions', 'wp-simple-firewall' ),
				'icon_class' => 'bi bi-person-badge',
				'href'       => $urls->investigateUserSessions(),
			],
			[
				'key'        => self::LIVE,
				'label'      => __( 'Live traffic', 'wp-simple-firewall' ),
				'icon_class' => 'bi bi-broadcast',
				'href'       => URL::Build( $urls->investigateHome(), [ 'subject' => 'live_traffic' ] ),
			],
		];

		return [
			'tabs'     => \array_map(
				static fn( array $tab ) :array => \array_merge( $tab, [ 'is_current' => $tab[ 'key' ] === $current ] ),
				$tabs
			),
			'ip_rules' => [
				'label' => __( 'IP rules', 'wp-simple-firewall' ),
				'href'  => $urls->adminIpRules(),
			],
			'label'    => __( 'Investigate', 'wp-simple-firewall' ),
		];
	}
}
