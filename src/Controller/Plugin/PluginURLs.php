<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\FullPageDisplay\DisplayReportAdmin,
	Actions\FileDownload,
	Actions\FileDownloadAsStream,
	Actions\Render\PluginAdminPages,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins\PluginPageView;
use FernleafSystems\Wordpress\Plugin\Shield\Enum\EnumModules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class PluginURLs {

	use PluginControllerConsumer;

	public function rootAdminPage() :string {
		return Services::WpGeneral()->getUrl_AdminPage(
			$this->rootAdminPageSlug(), (bool)self::con()->cfg->properties[ 'wpms_network_admin_only' ] );
	}

	public function rootAdminPageSlug() :string {
		return self::con()->prefix( EnumModules::PLUGIN );
	}

	public function adminHome() :string {
		return $this->adminTopNav( PluginNavs::NAV_DASHBOARD, PluginNavs::SUBNAV_DASHBOARD_OVERVIEW );
	}

	public function configureHome( string $zone = '' ) :string {
		$url = $this->adminTopNav( PluginNavs::NAV_ZONES, PluginNavs::SUBNAV_ZONES_OVERVIEW );
		$zone = sanitize_key( $zone );
		return empty( $zone ) ? $url : URL::Build( $url, [ 'zone' => $zone ] );
	}

	/**
	 * Configure, with the setup guide opened, even when it was finished or dismissed, optionally on one step.
	 */
	public function setupGuide( string $stepSlug = '' ) :string {
		return URL::Build( $this->configureHome(), \array_filter( [
			'setup_guide' => 'open',
			'setup_step'  => $stepSlug,
		] ) );
	}

	public function investigateHome() :string {
		return $this->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_OVERVIEW );
	}

	public function reportsHome( string $workspace = '' ) :string {
		$url = $this->adminTopNav( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_OVERVIEW );
		return isset( PluginNavs::reportsWorkspaceDefinitions()[ $workspace ] )
			? URL::Build( $url, [ 'workspace' => $workspace ] ) : $url;
	}

	public function reportView( string $reportID ) :string {
		$reportID = \trim( $reportID );

		return URL::Build(
			Services::WpGeneral()->getAdminUrl(
				'',
				(bool)self::con()->cfg->properties[ 'wpms_network_admin_only' ]
			),
			[
				ActionData::FIELD_ACTION  => ActionData::FIELD_SHIELD,
				ActionData::FIELD_EXECUTE => DisplayReportAdmin::SLUG,
				'report_unique_id'        => $reportID,
			]
		);
	}

	public function adminTopNav( string $nav, string $subNav = '' ) :string {
		return URL::Build( $this->rootAdminPage(), [
			Constants::NAV_ID     => sanitize_key( $nav ),
			Constants::NAV_SUB_ID => sanitize_key( $subNav ),
		] );
	}

	public function modeHome( string $mode ) :string {
		switch ( sanitize_key( $mode ) ) {
			case PluginNavs::MODE_ACTIONS:
				return $this->actionsQueueScans();

			case PluginNavs::MODE_INVESTIGATE:
				return $this->investigateHome();

			case PluginNavs::MODE_CONFIGURE:
				return $this->configureHome();

			case PluginNavs::MODE_REPORTS:
				return $this->reportsHome();

			default:
				return $this->adminHome();
		}
	}

	public function actionsQueueScans( string $zone = 'scans' ) :string {
		$url = $this->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_OVERVIEW );
		$zone = sanitize_key( $zone );
		return empty( $zone ) ? $url : URL::Build( $url, [ 'zone' => $zone ] );
	}

	public function cloakedPlugins() :string {
		return URL::Build(
			Services::WpGeneral()->getAdminUrl_Plugins(
				(bool)( self::con()->cfg->properties[ 'wpms_network_admin_only' ] ?? false )
			),
			[
				'plugin_status' => PluginPageView::STATUS,
			]
		);
	}

	/**
	 * The Plugins screen narrowed to one plugin: WordPress searches plugins by their name, within one of its
	 * views, such as Shield's Vulnerable view.
	 */
	public function pluginInPluginsScreen( string $pluginName, string $pluginStatus = 'all' ) :string {
		return URL::Build( Services::WpGeneral()->getAdminUrl_Plugins(), [
			'plugin_status' => $pluginStatus,
			's'             => $pluginName,
		] );
	}

	/**
	 * The Plugins screen's own Vulnerable view, which Shield adds.
	 */
	public function vulnerablePlugins() :string {
		return URL::Build( Services::WpGeneral()->getAdminUrl_Plugins(), [ 'plugin_status' => 'vulnerable' ] );
	}

	/**
	 * The Themes screen with one theme's details open.
	 */
	public function themeInThemesScreen( string $stylesheet ) :string {
		return URL::Build( Services::WpGeneral()->getAdminUrl_Themes(), [ 'theme' => $stylesheet ] );
	}

	public function scansRun() :string {
		return $this->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RUN );
	}

	/**
	 * Build a clean redirect URL from HTTP referer, preserving only nav parameters.
	 * Falls back to adminHome() if referer is invalid or not a Shield page.
	 */
	public function adminRefererOrHome() :string {
		$url = $this->adminHome();
		$referer = Services::Request()->server( 'HTTP_REFERER', '' );
		if ( !empty( $referer ) ) {

			$refererQuery = '';
			if ( \str_contains( $referer, '?' ) ) {
				[ , $refererQuery ] = \explode( '?', $referer, 2 );
			}
			if ( !empty( $refererQuery ) ) {
				\parse_str( $refererQuery, $queryParams );
				if ( ( $queryParams[ 'page' ] ?? '' ) === $this->rootAdminPageSlug() ) {
					$nav = sanitize_key( $queryParams[ Constants::NAV_ID ] ?? '' );
					$subNav = sanitize_key( $queryParams[ Constants::NAV_SUB_ID ] ?? '' );
					if ( PluginNavs::NavExists( $nav, $subNav ) ) {
						$url = $this->adminTopNav( $nav, $subNav );
					}
				}
			}
		}
		return $url;
	}

	public function adminIpRules() :string {
		return $this->adminTopNav( PluginNavs::NAV_IPS, PluginNavs::SUBNAV_IPS_RULES );
	}

	public function debugInfo() :string {
		return $this->adminTopNav( PluginNavs::NAV_TOOLS, PluginNavs::SUBNAV_TOOLS_DEBUG );
	}

	public function lockdown() :string {
		return $this->adminTopNav( PluginNavs::NAV_TOOLS, PluginNavs::SUBNAV_TOOLS_BLOCKDOWN );
	}

	public function ipAnalysis( string $ip ) :string {
		return URL::Build( $this->adminIpRules(), [ 'analyse_ip' => $ip ] );
	}

	public function investigateByIp( string $ip = '' ) :string {
		$url = $this->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_IP );
		if ( !empty( $ip ) ) {
			$url = URL::Build( $url, [ 'analyse_ip' => $ip ] );
		}
		return $url;
	}

	public function investigateByUser( string $lookup = '' ) :string {
		$url = $this->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_USER );
		if ( !empty( $lookup ) ) {
			$url = URL::Build( $url, [ 'user_lookup' => $lookup ] );
		}
		return $url;
	}

	public function investigateUserSessions() :string {
		return $this->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_SESSIONS );
	}

	public function investigateByPlugin( string $slug = '' ) :string {
		$url = $this->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN );
		if ( !empty( $slug ) ) {
			$url = URL::Build( $url, [ 'plugin_slug' => $slug ] );
		}
		return $url;
	}

	public function investigateByTheme( string $slug = '' ) :string {
		$url = $this->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_THEME );
		if ( !empty( $slug ) ) {
			$url = URL::Build( $url, [ 'theme_slug' => $slug ] );
		}
		return $url;
	}

	public function investigatePluginVulnerabilities( string $pluginFile = '' ) :string {
		return $this->investigateByPlugin( $pluginFile ).'#tab-navlink-plugin-vulnerabilities';
	}

	public function investigateThemeVulnerabilities( string $stylesheet = '' ) :string {
		return $this->investigateByTheme( $stylesheet ).'#tab-navlink-theme-vulnerabilities';
	}

	public function vulnerabilityLookupByPlugin( string $pluginSlug, string $version = '' ) :string {
		return URL::Build( 'https://clk.shldscrty.com/shieldvulnerabilitylookup', [
			'type'    => 'plugin',
			'slug'    => $pluginSlug,
			'version' => $version,
		] );
	}

	public function vulnerabilityLookupByTheme( string $stylesheet, string $version = '' ) :string {
		return URL::Build( 'https://clk.shldscrty.com/shieldvulnerabilitylookup', [
			'type'    => 'theme',
			'slug'    => $stylesheet,
			'version' => $version,
		] );
	}

	public function trafficLog() :string {
		return $this->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS );
	}

	public function trafficLive() :string {
		return $this->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LIVE );
	}

	public function cfgForZoneComponent( string $componentSlug ) :string {
		return $this->legacyAdminRouteRedirect( PluginNavs::NAV_ZONE_COMPONENTS, $componentSlug ) ?? $this->configureHome();
	}

	/**
	 * Where a setting is changed: its topic's panel in Configure, or the page that manages it.
	 */
	public function cfgForOpt( string $optKey ) :string {
		$route = self::optionRouteParams( $optKey, true );
		return $route === null ? $this->configureHome() : URL::Build( $this->rootAdminPage(), $route );
	}

	/**
	 * @return array<string,string>|null
	 */
	private static function optionRouteParams( string $optKey, bool $focusOption ) :?array {
		$ownPages = [
			'import_export' => PluginNavs::SUBNAV_TOOLS_IMPORT,
			'login_hide'    => PluginNavs::SUBNAV_TOOLS_LOGINHIDE,
			'whitelabel'    => PluginNavs::SUBNAV_TOOLS_WHITELABEL,
		];
		foreach ( PluginAdminPages\ConfigureGoalPanels::ownPageOptions() as $page => $optKeys ) {
			if ( isset( $ownPages[ $page ] ) && \in_array( $optKey, $optKeys, true ) ) {
				return [ 'nav' => PluginNavs::NAV_TOOLS, 'nav_sub' => $ownPages[ $page ] ];
			}
		}

		$topic = PluginAdminPages\ConfigureGoalPanels::topicForOption( $optKey );
		if ( $topic === null ) {
			return null;
		}
		$route = [
			'nav'     => PluginNavs::NAV_ZONES,
			'nav_sub' => PluginNavs::SUBNAV_ZONES_OVERVIEW,
			'zone'    => $topic[ 'goal' ],
			'row_key' => $topic[ 'topic' ],
		];
		if ( $focusOption ) {
			$route[ 'config_item' ] = $optKey;
		}
		return $route;
	}

	/**
	 * A component's settings are laid out in Configure topics; it opens on the topic holding the setting
	 * asked for, or else the place that holds most of its settings.
	 * @return array<string,string>|null
	 */
	private static function componentRouteParams( string $componentSlug, string $configItem ) :?array {
		// Hidden settings have no place to open, so they never produce a route.
		$options = self::con()->comps->zones->getZoneComponent( $componentSlug )->getOptions();
		if ( \in_array( $configItem, $options, true ) ) {
			$route = self::optionRouteParams( $configItem, true );
			if ( $route !== null ) {
				return $route;
			}
		}
		$routes = [];
		$counts = [];
		foreach ( $options as $optKey ) {
			$route = self::optionRouteParams( $optKey, false );
			if ( $route !== null ) {
				$id = \implode( '|', $route );
				$routes[ $id ] = $route;
				$counts[ $id ] = ( $counts[ $id ] ?? 0 ) + 1;
			}
		}
		// The first place wins a tie, as \arsort() keeps equal counts in order.
		\arsort( $counts );
		return empty( $counts ) ? null : $routes[ \array_key_first( $counts ) ];
	}

	/**
	 * Builds a URL with a nonce + any other auxiliary data for executing a Shield Plugin action
	 */
	public function noncedPluginAction( string $action, ?string $url = null, array $aux = [] ) :string {
		return URL::Build(
			empty( $url ) ? Services::WpGeneral()->getHomeUrl() : $url,
			ActionData::Build( $action, false, $aux )
		);
	}

	public function fileDownload( string $downloadCategory, array $params = [] ) :string {
		return $this->noncedPluginAction(
			FileDownload::class,
			Services::WpGeneral()->getAdminUrl(),
			\array_merge( $params, [ 'download_category' => $downloadCategory ] )
		);
	}

	public function fileDownloadAsStream( string $downloadCategory, array $params = [] ) :string {
		return $this->noncedPluginAction(
			FileDownloadAsStream::class,
			Services::WpGeneral()->getAdminUrl(),
			\array_merge( $params, [ 'download_category' => $downloadCategory ] )
		);
	}

	public function licenseCheck() :string {
		return $this->adminTopNav( PluginNavs::NAV_LICENSE, PluginNavs::SUBNAV_LICENSE_CHECK );
	}

	public function rulesBuild() :string {
		return $this->adminTopNav( PluginNavs::NAV_RULES, PluginNavs::SUBNAV_RULES_BUILD );
	}

	public function rulesManage() :string {
		return $this->adminTopNav( PluginNavs::NAV_RULES, PluginNavs::SUBNAV_RULES_MANAGE );
	}

	/**
	 * @param array<string,mixed> $query the rest of the old URL's query, for links that name a component
	 */
	public function legacyAdminRouteRedirect( string $nav, string $subNav, array $query = [] ) :?string {
		$route = self::legacyAdminRouteParams( $nav, $subNav, $query );
		if ( isset( $route[ 'wp_admin' ] ) ) {
			return Services::WpGeneral()->getAdminUrl( $route[ 'wp_admin' ], (bool)self::con()->cfg->properties[ 'wpms_network_admin_only' ] );
		}
		return $route === null ? null : URL::Build( $this->rootAdminPage(), $route );
	}

	/**
	 * @param array<string,mixed> $query the rest of the old URL's query, for links that name a component
	 * @return array<string,string>|null
	 */
	public static function legacyAdminRouteParams( string $nav, string $subNav, array $query = [] ) :?array {
		$nav = sanitize_key( $nav );
		$subNav = sanitize_key( $subNav );
		$configItem = \is_string( $query[ 'config_item' ] ?? null ) ? sanitize_key( $query[ 'config_item' ] ) : '';

		// Configure once opened a component's settings by name; they now live in topics.
		$component = \is_string( $query[ 'component' ] ?? null ) ? sanitize_key( $query[ 'component' ] ) : '';
		if ( $nav === PluginNavs::NAV_ZONES && $component !== '' ) {
			$nav = PluginNavs::NAV_ZONE_COMPONENTS;
			$subNav = $component;
		}

		if ( $nav === PluginNavs::NAV_SCANS ) {
			switch ( $subNav ) {
				case PluginNavs::SUBNAV_SCANS_RESULTS:
				case PluginNavs::SUBNAV_SCANS_HISTORY:
				case PluginNavs::SUBNAV_SCANS_STATE:
					return [ 'nav' => 'scans', 'nav_sub' => 'overview', 'zone' => 'scans' ];
			}
		}
		elseif ( $nav === PluginNavs::NAV_REPORTS ) {
			switch ( $subNav ) {
				case 'alerts':
				case 'reporting':
					$subNav = PluginNavs::SUBNAV_REPORTS_SETTINGS;
			}
			if ( isset( PluginNavs::reportsWorkspaceDefinitions()[ $subNav ] ) ) {
				return [ 'nav' => 'reports', 'nav_sub' => 'overview', 'workspace' => $subNav ];
			}
		}
		elseif ( $nav === PluginNavs::NAV_TOOLS && $subNav === PluginNavs::SUBNAV_TOOLS_SESSIONS ) {
			return [ 'nav' => 'activity', 'nav_sub' => 'sessions' ];
		}
		elseif ( $nav === PluginNavs::NAV_ZONE_COMPONENTS
				 && isset( self::con()->comps->zones->enumZoneComponents()[ $subNav ] ) ) {
			switch ( $subNav ) {
				case 'reporting':
					return [ 'nav' => 'reports', 'nav_sub' => 'overview', 'workspace' => 'settings' ];
				case 'activity_logging':
					return [ 'nav' => 'activity', 'nav_sub' => 'logs' ];
				case 'request_logging':
					return [ 'nav' => 'traffic', 'nav_sub' => 'logs' ];
				case 'scans':
					return [ 'nav' => 'scans', 'nav_sub' => 'overview' ];
				case 'server_software_status':
					return [ 'nav' => 'tools', 'nav_sub' => 'debug' ];
				case 'wordpress_updates':
					return [ 'wp_admin' => 'update-core.php' ];
				default:
					return self::componentRouteParams( $subNav, $configItem )
						   ?? [ 'nav' => PluginNavs::NAV_ZONES, 'nav_sub' => PluginNavs::SUBNAV_ZONES_OVERVIEW ];
			}
		}

		return null;
	}

	public function zone( string $zoneSlug ) :string {
		return $this->configureHome( $zoneSlug );
	}
}
