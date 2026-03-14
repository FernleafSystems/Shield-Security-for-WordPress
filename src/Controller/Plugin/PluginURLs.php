<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\FileDownload,
	Actions\FileDownloadAsStream,
	Constants
};
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

	public function configureHome() :string {
		return $this->adminTopNav( PluginNavs::NAV_ZONES, PluginNavs::SUBNAV_ZONES_OVERVIEW );
	}

	public function investigateHome() :string {
		return $this->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_OVERVIEW );
	}

	public function reportsHome() :string {
		return $this->adminTopNav( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_OVERVIEW );
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

	public function wizard( string $wizardKey ) :string {
		return $this->adminTopNav( PluginNavs::NAV_WIZARD, $wizardKey );
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

	public function investigateByCore() :string {
		return $this->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_CORE );
	}

	public function trafficLog() :string {
		return $this->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS );
	}

	public function trafficLive() :string {
		return $this->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LIVE );
	}

	public function cfgForZoneComponent( string $componentSlug ) :string {
		return $this->adminTopNav( PluginNavs::NAV_ZONE_COMPONENTS, $componentSlug );
	}

	public function cfgForOpt( string $optKey ) :string {
		$def = self::con()->opts->optDef( $optKey );
		if ( empty( $def ) || empty( $def[ 'zone_comp_slugs' ] ) ) {
			$def = self::con()->opts->optDef( 'visitor_address_source' );
		}
		return $this->cfgForZoneComponent( \current( $def[ 'zone_comp_slugs' ] ) );
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

	public function legacyAdminRouteRedirect( string $nav, string $subNav ) :?string {
		$nav = sanitize_key( $nav );
		$subNav = sanitize_key( $subNav );

		if ( $nav === PluginNavs::NAV_SCANS ) {
			switch ( $subNav ) {
				case PluginNavs::SUBNAV_SCANS_RESULTS:
				case PluginNavs::SUBNAV_SCANS_HISTORY:
				case PluginNavs::SUBNAV_SCANS_STATE:
					return $this->actionsQueueScans();
			}
		}

		return null;
	}

	public function zone( string $zoneSlug ) :string {
		return $this->adminTopNav( PluginNavs::NAV_ZONES, $zoneSlug );
	}
}
