<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Utilities\URL;

/**
 * The tabs across the top of Investigate: the lookup, the logs, IP rules and live traffic. On Investigate
 * itself each tab opens in place; elsewhere (the older log pages) each leads to Investigate with that tab
 * open, so Investigate reads as one place however it was reached.
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
				'label'      => __( 'Activity log', 'wp-simple-firewall' ),
				'icon_class' => 'bi bi-person-lines-fill',
			],
			[
				'key'        => self::REQUESTS,
				'label'      => __( 'Request log', 'wp-simple-firewall' ),
				'icon_class' => 'bi bi-globe',
			],
			[
				'key'        => self::IP_RULES,
				'label'      => __( 'IP rules', 'wp-simple-firewall' ),
				'icon_class' => 'bi bi-shield-lock',
			],
			[
				'key'        => self::SESSIONS,
				'label'      => __( 'Sessions', 'wp-simple-firewall' ),
				'icon_class' => 'bi bi-person-badge',
			],
			[
				'key'        => self::LIVE,
				'label'      => __( 'Live traffic', 'wp-simple-firewall' ),
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
		$home = self::con()->plugin_urls->investigateHome();
		return $tab === self::LOOKUP ? $home : URL::Build( $home, [ 'tab' => $tab ] );
	}

	/**
	 * @return list<string>
	 */
	public static function keys() :array {
		return [ self::LOOKUP, self::ACTIVITY, self::REQUESTS, self::IP_RULES, self::SESSIONS, self::LIVE ];
	}
}
