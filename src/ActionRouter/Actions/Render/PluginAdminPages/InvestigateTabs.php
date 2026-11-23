<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * The tabs across the top of Investigate: the lookup, the logs, IP rules and live traffic. Each tab has one
 * lasting link, the same one the menu uses, and on Investigate each opens in place.
 *
 * @phpstan-type InvestigateTab array{key:string,label:string,icon_class:string,href:string,is_current:bool}
 * @phpstan-type InvestigateTabsData array{tabs:list<InvestigateTab>,label:string}
 */
class InvestigateTabs {

	use PluginControllerConsumer;

	public const LOOKUP = 'lookup';
	public const ACTIVITY = 'activity';
	public const REQUESTS = 'requests';
	public const IP_RULES = 'ip_rules';
	public const SESSIONS = 'sessions';
	public const LIVE = 'live';

	/**
	 * @return InvestigateTabsData
	 */
	public function build( string $current ) :array {
		$tabs = [
			[
				'key'        => self::LOOKUP,
				'label'      => __( 'Lookup', 'wp-simple-firewall' ),
				'icon_class' => 'bi bi-search',
			],
			[
				'key'        => self::ACTIVITY,
				'label'      => __( 'WP Activity Log', 'wp-simple-firewall' ),
				'icon_class' => 'bi bi-person-lines-fill',
			],
			[
				'key'        => self::REQUESTS,
				'label'      => CommonDisplayStrings::get( 'web_request_log_label' ),
				'icon_class' => 'bi bi-globe',
			],
			[
				'key'        => self::IP_RULES,
				'label'      => __( 'IP Rules', 'wp-simple-firewall' ),
				'icon_class' => 'bi bi-shield-lock',
			],
			[
				'key'        => self::SESSIONS,
				'label'      => CommonDisplayStrings::get( 'user_sessions_label' ),
				'icon_class' => 'bi bi-person-badge',
			],
			[
				'key'        => self::LIVE,
				'label'      => __( 'Live Traffic', 'wp-simple-firewall' ),
				'icon_class' => 'bi bi-broadcast',
			],
		];

		return [
			'tabs'  => \array_map(
				fn( array $tab ) :array => \array_merge( $tab, [
					'href'       => $this->href( $tab[ 'key' ] ),
					'is_current' => $tab[ 'key' ] === $current,
				] ),
				$tabs
			),
			'label' => __( 'Investigate', 'wp-simple-firewall' ),
		];
	}

	public function href( string $tab ) :string {
		[ $nav, $subNav ] = self::routes()[ $tab ] ?? self::routes()[ self::LOOKUP ];
		return self::con()->plugin_urls->adminTopNav( $nav, $subNav );
	}

	/**
	 * The tab that a route opens, if it is one of the tabs' routes.
	 */
	public static function forRoute( string $nav, string $subNav ) :string {
		foreach ( self::routes() as $tab => $route ) {
			if ( $route === [ $nav, $subNav ] ) {
				return $tab;
			}
		}
		return '';
	}

	/**
	 * Each tab's route. The log, IP rules and sessions routes are the ones they have always had.
	 * @return array<string,array{string,string}>
	 */
	public static function routes() :array {
		return [
			self::LOOKUP   => [ PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_OVERVIEW ],
			self::ACTIVITY => [ PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS ],
			self::REQUESTS => [ PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS ],
			self::IP_RULES => [ PluginNavs::NAV_IPS, PluginNavs::SUBNAV_IPS_RULES ],
			self::SESSIONS => [ PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_SESSIONS ],
			self::LIVE     => [ PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LIVE ],
		];
	}
}
