<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\{
	ActivityLogging,
	InstantAlerts,
	PluginGeneral,
	Reporting,
	RequestLogging
};
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;
use FernleafSystems\Wordpress\Services\Services;

class PluginNavs {

	use PluginControllerConsumer;

	public const MODE_ACTIONS = 'actions';
	public const MODE_INVESTIGATE = 'investigate';
	public const MODE_CONFIGURE = 'configure';
	public const MODE_REPORTS = 'reports';
	public const FIELD_NAV = 'nav';
	public const FIELD_SUBNAV = 'nav_sub';
	public const NAV_ACTIVITY = 'activity';
	public const SUBNAV_ACTIVITY_OVERVIEW = 'overview';
	public const SUBNAV_ACTIVITY_BY_USER = 'by_user';
	public const SUBNAV_ACTIVITY_BY_IP = 'by_ip';
	public const SUBNAV_ACTIVITY_BY_PLUGIN = 'by_plugin';
	public const SUBNAV_ACTIVITY_BY_THEME = 'by_theme';
	public const SUBNAV_ACTIVITY_BY_CORE = 'by_core';
	public const SUBNAV_ACTIVITY_SESSIONS = 'sessions';
	public const NAV_IPS = 'ips';
	public const SUBNAV_IPS_RULES = 'rules';
	public const NAV_LICENSE = 'license';
	public const SUBNAV_LICENSE_CHECK = 'check';
	public const NAV_OPTIONS_CONFIG = 'config';
	public const NAV_DASHBOARD = 'dashboard';
	public const SUBNAV_DASHBOARD_OVERVIEW = 'overview';
	public const NAV_RESTRICTED = 'restricted';
	public const NAV_REPORTS = 'reports';
	public const SUBNAV_REPORTS_OVERVIEW = 'overview';
	public const SUBNAV_REPORTS_LIST = 'list';
	public const SUBNAV_REPORTS_ALERTS = 'alerts';
	public const SUBNAV_REPORTS_REPORTING = 'reporting';
	public const SUBNAV_REPORTS_CHARTS = 'charts';
	public const SUBNAV_REPORTS_SETTINGS = 'settings';
	public const NAV_RULES = 'rules';
	public const SUBNAV_RULES_MANAGE = 'manage';
	public const SUBNAV_RULES_BUILD = 'build';
	public const SUBNAV_RULES_SUMMARY = 'summary';
	public const NAV_SCANS = 'scans';
	public const SUBNAV_SCANS_OVERVIEW = 'overview';
	public const SUBNAV_SCANS_STATE = 'state';
	public const SUBNAV_SCANS_HISTORY = 'history';
	public const SUBNAV_SCANS_RESULTS = 'results';
	public const SUBNAV_SCANS_RUN = 'run';
	public const NAV_STATS = 'stats';
	public const NAV_TRAFFIC = 'traffic';
	public const SUBNAV_LIVE = 'live';
	public const NAV_TOOLS = 'tools';
	public const SUBNAV_TOOLS_DEBUG = 'debug';
	public const SUBNAV_TOOLS_IMPORT = 'importexport';
	public const SUBNAV_TOOLS_BLOCKDOWN = 'blockdown';
	public const SUBNAV_TOOLS_SESSIONS = 'sessions';
	public const NAV_WIZARD = 'merlin';
	public const NAV_ZONES = 'zones';
	public const SUBNAV_ZONES_OVERVIEW = 'overview';
	public const NAV_ZONE_COMPONENTS = 'zone_components';
	public const SUBNAV_WIZARD_WELCOME = 'welcome';
	public const SUBNAV_INDEX = 'index'; /* special case used only to indicate pick first in subnav list, for now */
	public const SUBNAV_LOGS = 'logs';

	public static function GetNav() :string {
		return sanitize_key( (string)Services::Request()->query( self::FIELD_NAV ) );
	}

	public static function GetSubNav() :string {
		return sanitize_key( (string)Services::Request()->query( self::FIELD_SUBNAV ) );
	}

	public static function IsNavs( string $nav, string $subNav ) :bool {
		return self::GetNav() === $nav && self::GetSubNav() === $subNav;
	}

	public static function GetDefaultSubNavForNav( string $nav ) :string {
		return $nav === self::NAV_ZONES
			? self::SUBNAV_ZONES_OVERVIEW
			: \key( PluginNavs::GetNavHierarchy()[ $nav ][ 'sub_navs' ] );
	}

	public static function GetNavHierarchy() :array {
		return \array_map(
			[ self::class, 'normalizeNavDefinition' ],
			[
				self::NAV_ACTIVITY        => self::activityNavDefinition(),
				self::NAV_DASHBOARD       => self::dashboardNavDefinition(),
				self::NAV_IPS             => self::ipsNavDefinition(),
				self::NAV_LICENSE         => self::licenseNavDefinition(),
				self::NAV_REPORTS         => self::reportsNavDefinition(),
				self::NAV_RESTRICTED      => self::restrictedNavDefinition(),
				self::NAV_RULES           => self::rulesNavDefinition(),
				self::NAV_SCANS           => self::scansNavDefinition(),
				self::NAV_TOOLS           => self::toolsNavDefinition(),
				self::NAV_TRAFFIC         => self::trafficNavDefinition(),
				self::NAV_WIZARD          => self::wizardNavDefinition(),
				self::NAV_ZONES           => self::zonesNavDefinition(),
				self::NAV_ZONE_COMPONENTS => self::zoneComponentsNavDefinition(),
			]
		);
	}

	public static function NavExists( string $nav, ?string $subNav = null ) :bool {
		return isset( self::GetNavHierarchy()[ $nav ] )
			   && ( $subNav === null || isset( self::GetNavHierarchy()[ $nav ][ 'sub_navs' ][ $subNav ] ) );
	}

	public static function allOperatorModes() :array {
		return [
			self::MODE_ACTIONS,
			self::MODE_INVESTIGATE,
			self::MODE_CONFIGURE,
			self::MODE_REPORTS,
		];
	}

	public static function modeForNav( string $nav ) :string {
		switch ( $nav ) {
			case self::NAV_SCANS:
				return self::MODE_ACTIONS;

			case self::NAV_ACTIVITY:
			case self::NAV_IPS:
			case self::NAV_TRAFFIC:
				return self::MODE_INVESTIGATE;

			case self::NAV_ZONES:
			case self::NAV_RULES:
			case self::NAV_TOOLS:
			case self::NAV_ZONE_COMPONENTS:
			case self::NAV_OPTIONS_CONFIG:
			case self::NAV_WIZARD:
			case self::NAV_LICENSE:
				return self::MODE_CONFIGURE;

			case self::NAV_REPORTS:
				return self::MODE_REPORTS;

			case self::NAV_DASHBOARD:
			case self::NAV_RESTRICTED:
			default:
				return '';
		}
	}

	public static function modeForRoute( string $nav, string $subNav ) :string {
		return self::modeForNav( $nav );
	}

	public static function modeLandingSubNavsByNav() :array {
		return [
			self::NAV_SCANS    => [
				self::SUBNAV_SCANS_OVERVIEW,
			],
			self::NAV_ACTIVITY => [
				self::SUBNAV_ACTIVITY_OVERVIEW,
			],
			self::NAV_ZONES    => [
				self::SUBNAV_ZONES_OVERVIEW,
			],
			self::NAV_REPORTS  => [
				self::SUBNAV_REPORTS_OVERVIEW,
			],
		];
	}

	public static function isModeLandingRoute( string $nav, string $subNav ) :bool {
		$landingRoutes = self::modeLandingSubNavsByNav();
		return isset( $landingRoutes[ $nav ] )
			   && \in_array( $subNav, $landingRoutes[ $nav ], true );
	}

	public static function defaultEntryForMode( string $mode ) :array {
		$mode = self::sanitizeOperatorMode( $mode );

		switch ( $mode ) {
			case self::MODE_ACTIONS:
				$entry = [
					'nav'    => self::NAV_SCANS,
					'subnav' => self::SUBNAV_SCANS_OVERVIEW,
				];
				break;
			case self::MODE_INVESTIGATE:
				$entry = [
					'nav'    => self::NAV_ACTIVITY,
					'subnav' => self::SUBNAV_ACTIVITY_OVERVIEW,
				];
				break;
			case self::MODE_CONFIGURE:
				$entry = [
					'nav'    => self::NAV_ZONES,
					'subnav' => self::SUBNAV_ZONES_OVERVIEW,
				];
				break;
			case self::MODE_REPORTS:
				$entry = [
					'nav'    => self::NAV_REPORTS,
					'subnav' => self::SUBNAV_REPORTS_OVERVIEW,
				];
				break;
			default:
				$entry = [
					'nav'    => self::NAV_DASHBOARD,
					'subnav' => self::SUBNAV_DASHBOARD_OVERVIEW,
				];
				break;
		}

		return $entry;
	}

	public static function modeLabel( string $mode ) :string {
		$mode = self::sanitizeOperatorMode( $mode );

		switch ( $mode ) {
			case self::MODE_ACTIONS:
				return __( 'Actions Queue', 'wp-simple-firewall' );

			case self::MODE_INVESTIGATE:
				return __( 'Investigate', 'wp-simple-firewall' );

			case self::MODE_CONFIGURE:
				return __( 'Configure', 'wp-simple-firewall' );

			case self::MODE_REPORTS:
				return __( 'Reports', 'wp-simple-firewall' );

			default:
				return __( 'Mode Selector', 'wp-simple-firewall' );
		}
	}

	/**
	 * Producer contract for Investigate landing subject definitions.
	 * Internal consumers must rely on this shape directly and avoid inline defensive casts.
	 *
	 * @return array<string,array{
	 *   label:string,
	 *   icon_class:string,
	 *   status:string,
	 *   stat_text:string,
	 *   subnav_hint:string|null,
	 *   panel_title:string,
	 *   panel_status:string,
	 *   render_action:string,
	 *   render_nav:string,
	 *   render_subnav:string,
	 *   lookup_key:string|null,
	 *   is_enabled:bool,
	 *   is_pro:bool
	 * }>
	 */
	public static function investigateLandingSubjectDefinitions() :array {
		return [
			'user'                 => [
				'label'         => __( 'User', 'wp-simple-firewall' ),
				'icon_class'    => 'bi bi-person',
				'status'        => 'info',
				'stat_text'     => __( 'Sessions and activity', 'wp-simple-firewall' ),
				'subnav_hint'   => self::SUBNAV_ACTIVITY_BY_USER,
				'panel_title'   => __( 'Investigate User', 'wp-simple-firewall' ),
				'panel_status'  => 'info',
				'render_action' => PluginAdminPages\PageInvestigateByUser::class,
				'render_nav'    => self::NAV_ACTIVITY,
				'render_subnav' => self::SUBNAV_ACTIVITY_BY_USER,
				'lookup_key'    => 'user_lookup',
				'is_enabled'    => true,
				'is_pro'        => false,
			],
			'ip'                   => [
				'label'         => __( 'IP Address', 'wp-simple-firewall' ),
				'icon_class'    => 'bi bi-globe',
				'status'        => 'info',
				'stat_text'     => __( 'Requests and activity', 'wp-simple-firewall' ),
				'subnav_hint'   => self::SUBNAV_ACTIVITY_BY_IP,
				'panel_title'   => __( 'Investigate IP Address', 'wp-simple-firewall' ),
				'panel_status'  => 'info',
				'render_action' => PluginAdminPages\PageInvestigateByIp::class,
				'render_nav'    => self::NAV_ACTIVITY,
				'render_subnav' => self::SUBNAV_ACTIVITY_BY_IP,
				'lookup_key'    => 'analyse_ip',
				'is_enabled'    => true,
				'is_pro'        => false,
			],
			'plugin'               => [
				'label'         => __( 'Plugin', 'wp-simple-firewall' ),
				'icon_class'    => 'bi bi-plug',
				'status'        => 'info',
				'stat_text'     => __( 'Installed plugin events', 'wp-simple-firewall' ),
				'subnav_hint'   => self::SUBNAV_ACTIVITY_BY_PLUGIN,
				'panel_title'   => __( 'Investigate Plugin', 'wp-simple-firewall' ),
				'panel_status'  => 'info',
				'render_action' => PluginAdminPages\PageInvestigateByPlugin::class,
				'render_nav'    => self::NAV_ACTIVITY,
				'render_subnav' => self::SUBNAV_ACTIVITY_BY_PLUGIN,
				'lookup_key'    => 'plugin_slug',
				'is_enabled'    => true,
				'is_pro'        => false,
			],
			'theme'                => [
				'label'         => __( 'Theme', 'wp-simple-firewall' ),
				'icon_class'    => 'bi bi-brush',
				'status'        => 'info',
				'stat_text'     => __( 'Installed theme events', 'wp-simple-firewall' ),
				'subnav_hint'   => self::SUBNAV_ACTIVITY_BY_THEME,
				'panel_title'   => __( 'Investigate Theme', 'wp-simple-firewall' ),
				'panel_status'  => 'info',
				'render_action' => PluginAdminPages\PageInvestigateByTheme::class,
				'render_nav'    => self::NAV_ACTIVITY,
				'render_subnav' => self::SUBNAV_ACTIVITY_BY_THEME,
				'lookup_key'    => 'theme_slug',
				'is_enabled'    => true,
				'is_pro'        => false,
			],
			'core'                 => [
				'label'         => __( 'Core Files', 'wp-simple-firewall' ),
				'icon_class'    => 'bi bi-wordpress',
				'status'        => 'info',
				'stat_text'     => __( 'Integrity and activity', 'wp-simple-firewall' ),
				'subnav_hint'   => self::SUBNAV_ACTIVITY_BY_CORE,
				'panel_title'   => __( 'Investigate Core Files', 'wp-simple-firewall' ),
				'panel_status'  => 'info',
				'render_action' => PluginAdminPages\PageInvestigateByCore::class,
				'render_nav'    => self::NAV_ACTIVITY,
				'render_subnav' => self::SUBNAV_ACTIVITY_BY_CORE,
				'lookup_key'    => null,
				'is_enabled'    => true,
				'is_pro'        => false,
			],
			'live_traffic'         => [
				'label'         => __( 'Live Traffic', 'wp-simple-firewall' ),
				'icon_class'    => 'bi bi-lightning',
				'status'        => 'info',
				'stat_text'     => __( 'Live request stream', 'wp-simple-firewall' ),
				'subnav_hint'   => null,
				'panel_title'   => __( 'Live Traffic', 'wp-simple-firewall' ),
				'panel_status'  => 'info',
				'render_action' => PluginAdminPages\PageTrafficLogLive::class,
				'render_nav'    => self::NAV_TRAFFIC,
				'render_subnav' => self::SUBNAV_LIVE,
				'lookup_key'    => null,
				'is_enabled'    => true,
				'is_pro'        => false,
			],
			'premium_integrations' => [
				'label'         => __( 'Premium Integrations', 'wp-simple-firewall' ),
				'icon_class'    => 'bi bi-stars',
				'status'        => 'neutral',
				'stat_text'     => __( 'Coming soon', 'wp-simple-firewall' ),
				'subnav_hint'   => null,
				'panel_title'   => __( 'Premium Integrations', 'wp-simple-firewall' ),
				'panel_status'  => 'neutral',
				'render_action' => '',
				'render_nav'    => '',
				'render_subnav' => '',
				'lookup_key'    => null,
				'is_enabled'    => false,
				'is_pro'        => true,
			],
		];
	}

	public static function investigateSubjectKeyForSubNav( string $subNav ) :string {
		$subNav = sanitize_key( $subNav );
		if ( empty( $subNav ) ) {
			return '';
		}

		foreach ( self::investigateLandingSubjectDefinitions() as $subjectKey => $subject ) {
			if ( ( $subject[ 'subnav_hint' ] ?? null ) === $subNav ) {
				return $subjectKey;
			}
		}

		return '';
	}

	public static function isInvestigateLegacyContextSubNav( string $subNav ) :bool {
		return self::investigateSubjectKeyForSubNav( $subNav ) !== '';
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   label:string,
	 *   icon:string,
	 *   zone_slug?:string,
	 *   component_slug?:string,
	 *   component_slugs?:list<string>,
	 *   include_in_posture?:bool,
	 *   force_neutral?:bool
	 * }>
	 */
	public static function configureLandingTileDefinitions() :array {
		return [
			[
				'key'      => 'secadmin',
				'label'    => __( 'Security Admin', 'wp-simple-firewall' ),
				'icon'     => 'shield-lock',
				'zone_slug' => Zone\Secadmin::Slug(),
			],
			[
				'key'      => 'firewall',
				'label'    => __( 'Firewall', 'wp-simple-firewall' ),
				'icon'     => 'fire',
				'zone_slug' => Zone\Firewall::Slug(),
			],
			[
				'key'      => 'ips',
				'label'    => __( 'Bots and IPs', 'wp-simple-firewall' ),
				'icon'     => 'robot',
				'zone_slug' => Zone\Ips::Slug(),
			],
			[
				'key'      => 'scans',
				'label'    => __( 'Scans', 'wp-simple-firewall' ),
				'icon'     => 'bug',
				'zone_slug' => Zone\Scans::Slug(),
			],
			[
				'key'      => 'login',
				'label'    => __( 'Login', 'wp-simple-firewall' ),
				'icon'     => 'person-lock',
				'zone_slug' => Zone\Login::Slug(),
			],
			[
				'key'      => 'users',
				'label'    => __( 'Users', 'wp-simple-firewall' ),
				'icon'     => 'people',
				'zone_slug' => Zone\Users::Slug(),
			],
			[
				'key'      => 'spam',
				'label'    => __( 'SPAM', 'wp-simple-firewall' ),
				'icon'     => 'chat-dots',
				'zone_slug' => Zone\Spam::Slug(),
			],
			[
				'key'      => 'headers',
				'label'    => __( 'HTTP Headers', 'wp-simple-firewall' ),
				'icon'     => 'file-earmark-lock',
				'zone_slug' => Zone\Headers::Slug(),
			],
			[
				'key'                => 'general',
				'label'              => __( 'General', 'wp-simple-firewall' ),
				'icon'               => 'sliders',
				'component_slugs'    => [
					PluginGeneral::Slug(),
					ActivityLogging::Slug(),
					RequestLogging::Slug(),
				],
				'include_in_posture' => false,
				'force_neutral'      => true,
			],
		];
	}

	/**
	 * @return array<string,array{
	 *   slug:string,
	 *   label:string,
	 *   icon:string
	 * }>
	 */
	public static function actionsLandingZoneDefinitions() :array {
		return [
			'scans'       => [
				'slug'  => 'scans',
				'label' => __( 'Scans', 'wp-simple-firewall' ),
				'icon'  => 'shield-exclamation',
			],
			'maintenance' => [
				'slug'  => 'maintenance',
				'label' => __( 'Maintenance', 'wp-simple-firewall' ),
				'icon'  => 'wrench',
			],
		];
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   zone:string,
	 *   component_class:class-string<Component\Base>,
	 *   availability_strategy:string
	 * }>
	 */
	public static function actionsLandingAssessmentDefinitions() :array {
		return [
			[
				'key'                   => 'wp_files',
				'zone'                  => 'scans',
				'component_class'       => Component\ScanResultsWcf::class,
				'availability_strategy' => 'scan_afs_core_enabled',
			],
			[
				'key'                   => 'plugin_theme_files',
				'zone'                  => 'scans',
				'component_class'       => Component\ScanResultsPtg::class,
				'availability_strategy' => 'scan_afs_plugins_and_themes_enabled',
			],
			[
				'key'                   => 'malware',
				'zone'                  => 'scans',
				'component_class'       => Component\ScanResultsMal::class,
				'availability_strategy' => 'scan_malware_enabled',
			],
			[
				'key'                   => 'vulnerable_assets',
				'zone'                  => 'scans',
				'component_class'       => Component\ScanResultsWpv::class,
				'availability_strategy' => 'scan_wpv_enabled',
			],
			[
				'key'                   => 'abandoned',
				'zone'                  => 'scans',
				'component_class'       => Component\ScanResultsApc::class,
				'availability_strategy' => 'scan_apc_enabled',
			],
			[
				'key'                   => 'wp_updates',
				'zone'                  => 'maintenance',
				'component_class'       => Component\WpUpdates::class,
				'availability_strategy' => 'always',
			],
			[
				'key'                   => 'wp_plugins_updates',
				'zone'                  => 'maintenance',
				'component_class'       => Component\WpPluginsUpdates::class,
				'availability_strategy' => 'always',
			],
			[
				'key'                   => 'wp_themes_updates',
				'zone'                  => 'maintenance',
				'component_class'       => Component\WpThemesUpdates::class,
				'availability_strategy' => 'always',
			],
		];
	}

	/**
	 * @return array<string,array{
	 *   menu_title:string,
	 *   landing_cta:string,
	 *   page_title:string,
	 *   page_subtitle:string,
	 *   content_key:string,
	 *   render_action:string,
	 *   show_create_action:bool,
	 *   show_in_sidebar:bool,
	 *   show_on_landing:bool,
	 *   config_zone_component_slugs:list<string>
	 * }>
	 */
	public static function reportsWorkspaceDefinitions() :array {
		return [
			self::SUBNAV_REPORTS_LIST     => [
				'menu_title'         => __( 'Security Reports', 'wp-simple-firewall' ),
				'landing_cta'        => __( 'Open Reports List', 'wp-simple-firewall' ),
				'page_title'         => __( 'View & Create', 'wp-simple-firewall' ),
				'page_subtitle'      => __( 'View and create new security reports.', 'wp-simple-firewall' ),
				'content_key'        => 'create_report',
				'render_action'      => Reports\PageReportsView::class,
				'show_create_action' => true,
				'show_in_sidebar'    => true,
				'show_on_landing'    => true,
				'config_zone_component_slugs' => [],
			],
			self::SUBNAV_REPORTS_ALERTS   => [
				'menu_title'         => __( 'Alert Settings', 'wp-simple-firewall' ),
				'landing_cta'        => __( 'Open Alert Settings', 'wp-simple-firewall' ),
				'page_title'         => __( 'Alert Settings', 'wp-simple-firewall' ),
				'page_subtitle'      => __( 'Manage instant alerts for important security events.', 'wp-simple-firewall' ),
				'content_key'        => 'alerts_settings',
				'render_action'      => OptionsFormFor::class,
				'show_create_action' => false,
				'show_in_sidebar'    => false,
				'show_on_landing'    => false,
				'config_zone_component_slugs' => self::reportsAlertSettingsZoneComponentSlugs(),
			],
			self::SUBNAV_REPORTS_REPORTING => [
				'menu_title'         => __( 'Reporting Configuration', 'wp-simple-firewall' ),
				'landing_cta'        => __( 'Open Reporting Configuration', 'wp-simple-firewall' ),
				'page_title'         => __( 'Reporting Configuration', 'wp-simple-firewall' ),
				'page_subtitle'      => __( 'Manage report generation and delivery preferences.', 'wp-simple-firewall' ),
				'content_key'        => 'reporting_configuration',
				'render_action'      => OptionsFormFor::class,
				'show_create_action' => false,
				'show_in_sidebar'    => false,
				'show_on_landing'    => false,
				'config_zone_component_slugs' => self::reportsReportingConfigurationZoneComponentSlugs(),
			],
			self::SUBNAV_REPORTS_CHARTS   => [
				'menu_title'         => __( 'Charts & Trends', 'wp-simple-firewall' ),
				'landing_cta'        => __( 'Open Charts & Trends', 'wp-simple-firewall' ),
				'page_title'         => __( 'Charts & Trends', 'wp-simple-firewall' ),
				'page_subtitle'      => __( 'Review recent security trend metrics.', 'wp-simple-firewall' ),
				'content_key'        => 'summary_charts',
				'render_action'      => Reports\ChartsSummary::class,
				'show_create_action' => false,
				'show_in_sidebar'    => false,
				'show_on_landing'    => false,
				'config_zone_component_slugs' => [],
			],
			self::SUBNAV_REPORTS_SETTINGS => [
				'menu_title'         => __( 'Reporting & Alerts Configuration', 'wp-simple-firewall' ),
				'landing_cta'        => __( 'Open Reporting & Alerts Configuration', 'wp-simple-firewall' ),
				'page_title'         => __( 'Reporting & Alerts Configuration', 'wp-simple-firewall' ),
				'page_subtitle'      => __( 'Manage instant alerts and report delivery settings together.', 'wp-simple-firewall' ),
				'content_key'        => 'reporting_alerts_configuration',
				'render_action'      => OptionsFormFor::class,
				'show_create_action' => false,
				'show_in_sidebar'    => true,
				'show_on_landing'    => true,
				'config_zone_component_slugs' => self::reportsSettingsZoneComponentSlugs(),
			],
		];
	}

	/**
	 * @return array<string,array{
	 *   menu_title:string,
	 *   landing_cta:string,
	 *   page_title:string,
	 *   page_subtitle:string,
	 *   content_key:string,
	 *   render_action:string,
	 *   show_create_action:bool,
	 *   show_in_sidebar:bool,
	 *   show_on_landing:bool,
	 *   config_zone_component_slugs:list<string>
	 * }>
	 */
	public static function reportsSidebarWorkspaceDefinitions() :array {
		return \array_filter(
			self::reportsWorkspaceDefinitions(),
			static fn( array $definition ) :bool => (bool)( $definition[ 'show_in_sidebar' ] ?? false )
		);
	}

	/**
	 * @return array<string,array{
	 *   menu_title:string,
	 *   landing_cta:string,
	 *   page_title:string,
	 *   page_subtitle:string,
	 *   content_key:string,
	 *   render_action:string,
	 *   show_create_action:bool,
	 *   show_in_sidebar:bool,
	 *   show_on_landing:bool,
	 *   config_zone_component_slugs:list<string>
	 * }>
	 */
	public static function reportsLandingWorkspaceDefinitions() :array {
		return \array_filter(
			self::reportsWorkspaceDefinitions(),
			static fn( array $definition ) :bool => (bool)( $definition[ 'show_on_landing' ] ?? false )
		);
	}

	public static function reportsRouteHandlers() :array {
		return \array_merge(
			[
				self::SUBNAV_REPORTS_OVERVIEW => PluginAdminPages\PageReportsLanding::class,
			],
			\array_fill_keys(
				\array_keys( self::reportsWorkspaceDefinitions() ),
				PluginAdminPages\PageReports::class
			)
		);
	}

	public static function reportsDefaultWorkspaceSubNav() :string {
		$subNav = \key( self::reportsWorkspaceDefinitions() );
		return \is_string( $subNav ) && !empty( $subNav )
			? $subNav
			: self::SUBNAV_REPORTS_LIST;
	}

	public static function reportsAlertSettingsZoneComponentSlugs() :array {
		return [
			InstantAlerts::Slug(),
		];
	}

	public static function reportsReportingConfigurationZoneComponentSlugs() :array {
		return [
			Reporting::Slug(),
		];
	}

	public static function reportsSettingsZoneComponentSlugs() :array {
		return \array_merge(
			self::reportsAlertSettingsZoneComponentSlugs(),
			self::reportsReportingConfigurationZoneComponentSlugs()
		);
	}

	private static function sanitizeOperatorMode( string $mode ) :string {
		$mode = \strtolower( \trim( $mode ) );
		return \in_array( $mode, self::allOperatorModes(), true ) ? $mode : '';
	}

	private static function normalizeNavDefinition( array $nav ) :array {
		if ( !isset( $nav[ 'parents' ] ) ) {
			$nav[ 'parents' ] = [];
		}
		if ( !\in_array( self::NAV_DASHBOARD, $nav[ 'parents' ], true ) ) {
			$nav[ 'parents' ][] = self::NAV_DASHBOARD;
		}
		return $nav;
	}

	private static function activityNavDefinition() :array {
		$labels = self::activityBreadcrumbLabels();
		return [
			'name'     => __( 'Activity', 'wp-simple-firewall' ),
			'sub_navs' => [
				self::SUBNAV_ACTIVITY_OVERVIEW  => self::routeDefinition( PluginAdminPages\PageInvestigateLanding::class ),
				self::SUBNAV_ACTIVITY_BY_USER   => self::routeDefinition( PluginAdminPages\PageInvestigateByUser::class, $labels[ self::SUBNAV_ACTIVITY_BY_USER ] ?? '' ),
				self::SUBNAV_ACTIVITY_BY_IP     => self::routeDefinition( PluginAdminPages\PageInvestigateByIp::class, $labels[ self::SUBNAV_ACTIVITY_BY_IP ] ?? '' ),
				self::SUBNAV_ACTIVITY_BY_PLUGIN => self::routeDefinition( PluginAdminPages\PageInvestigateByPlugin::class, $labels[ self::SUBNAV_ACTIVITY_BY_PLUGIN ] ?? '' ),
				self::SUBNAV_ACTIVITY_BY_THEME  => self::routeDefinition( PluginAdminPages\PageInvestigateByTheme::class, $labels[ self::SUBNAV_ACTIVITY_BY_THEME ] ?? '' ),
				self::SUBNAV_ACTIVITY_BY_CORE   => self::routeDefinition( PluginAdminPages\PageInvestigateByCore::class, $labels[ self::SUBNAV_ACTIVITY_BY_CORE ] ?? '' ),
				self::SUBNAV_ACTIVITY_SESSIONS  => self::routeDefinition( PluginAdminPages\PageUserSessions::class, $labels[ self::SUBNAV_ACTIVITY_SESSIONS ] ?? '' ),
				self::SUBNAV_LOGS               => self::routeDefinition( PluginAdminPages\PageActivityLogTable::class, $labels[ self::SUBNAV_LOGS ] ?? '' ),
			],
		];
	}

	private static function dashboardNavDefinition() :array {
		return [
			'name'     => __( 'Dashboard', 'wp-simple-firewall' ),
			'sub_navs' => [
				self::SUBNAV_DASHBOARD_OVERVIEW => self::routeDefinition( PluginAdminPages\PageDashboardOverview::class ),
			],
		];
	}

	private static function ipsNavDefinition() :array {
		return [
			'name'     => __( 'IPs', 'wp-simple-firewall' ),
			'sub_navs' => [
				self::SUBNAV_IPS_RULES => self::routeDefinition( PluginAdminPages\PageIpRulesTable::class, __( 'Bots & IP Rules', 'wp-simple-firewall' ) ),
			],
		];
	}

	private static function licenseNavDefinition() :array {
		return [
			'name'     => __( 'License', 'wp-simple-firewall' ),
			'sub_navs' => [
				self::SUBNAV_LICENSE_CHECK => self::routeDefinition( PluginAdminPages\PageLicense::class ),
			],
		];
	}

	private static function reportsNavDefinition() :array {
		return [
			'name'     => __( 'Reports', 'wp-simple-firewall' ),
			'sub_navs' => self::reportsSubNavDefinitions(),
		];
	}

	private static function restrictedNavDefinition() :array {
		return [
			'name'     => __( 'Restricted', 'wp-simple-firewall' ),
			'sub_navs' => [
				self::SUBNAV_INDEX => self::routeDefinition( PluginAdminPages\PageSecurityAdminRestricted::class ),
			],
		];
	}

	private static function rulesNavDefinition() :array {
		return [
			'name'     => __( 'Rules', 'wp-simple-firewall' ),
			'sub_navs' => [
				self::SUBNAV_RULES_MANAGE  => self::routeDefinition( PluginAdminPages\PageRulesManage::class ),
				self::SUBNAV_RULES_BUILD   => self::routeDefinition( PluginAdminPages\PageRulesBuild::class ),
				self::SUBNAV_RULES_SUMMARY => self::routeDefinition( PluginAdminPages\PageRulesSummary::class ),
			],
		];
	}

	private static function scansNavDefinition() :array {
		return [
			'name'     => __( 'Scans', 'wp-simple-firewall' ),
			'sub_navs' => [
				self::SUBNAV_SCANS_OVERVIEW => self::routeDefinition( PluginAdminPages\PageActionsQueueLanding::class ),
				self::SUBNAV_SCANS_RESULTS  => self::routeDefinition( PluginAdminPages\PageScansResults::class, __( 'Scan Results', 'wp-simple-firewall' ) ),
				self::SUBNAV_SCANS_RUN      => self::routeDefinition( PluginAdminPages\PageScansRun::class, __( 'Run Scan', 'wp-simple-firewall' ) ),
			],
		];
	}

	private static function toolsNavDefinition() :array {
		return [
			'name'     => __( 'Tools', 'wp-simple-firewall' ),
			'sub_navs' => [
				self::SUBNAV_TOOLS_BLOCKDOWN => self::routeDefinition( PluginAdminPages\PageToolLockdown::class ),
				self::SUBNAV_TOOLS_SESSIONS  => self::routeDefinition( PluginAdminPages\PageUserSessions::class ),
				self::SUBNAV_TOOLS_DEBUG     => self::routeDefinition( PluginAdminPages\PageDebug::class, __( 'Debug Info', 'wp-simple-firewall' ) ),
				self::SUBNAV_TOOLS_IMPORT    => self::routeDefinition( PluginAdminPages\PageImportExport::class ),
			],
		];
	}

	private static function trafficNavDefinition() :array {
		return [
			'name'     => __( 'Traffic', 'wp-simple-firewall' ),
			'sub_navs' => [
				self::SUBNAV_LOGS => self::routeDefinition( PluginAdminPages\PageTrafficLogTable::class, __( 'HTTP Request Log', 'wp-simple-firewall' ) ),
				self::SUBNAV_LIVE => self::routeDefinition( PluginAdminPages\PageTrafficLogLive::class, __( 'Live HTTP Log', 'wp-simple-firewall' ) ),
			],
		];
	}

	private static function wizardNavDefinition() :array {
		return [
			'name'     => __( 'Wizards', 'wp-simple-firewall' ),
			'sub_navs' => [
				self::SUBNAV_WIZARD_WELCOME => self::routeDefinition( PluginAdminPages\PageMerlin::class ),
			],
		];
	}

	private static function zonesNavDefinition() :array {
		return [
			'name'     => __( 'Security Zones', 'wp-simple-firewall' ),
			'sub_navs' => \array_merge(
				[
					self::SUBNAV_ZONES_OVERVIEW => self::routeDefinition( PluginAdminPages\PageConfigureLanding::class ),
				],
				\array_map(
					fn() :array => self::routeDefinition( PluginAdminPages\PageDynamicLoad::class ),
					\array_flip( \array_keys( self::con()->comps->zones->enumZones() ) )
				)
			),
		];
	}

	private static function zoneComponentsNavDefinition() :array {
		return [
			'name'     => __( 'Security Zones Config', 'wp-simple-firewall' ),
			'sub_navs' => \array_map(
				fn() :array => self::routeDefinition( PluginAdminPages\PageZoneComponentConfig::class ),
				\array_flip( \array_keys( self::con()->comps->zones->enumZoneComponents() ) )
			),
		];
	}

	private static function routeDefinition( string $handler, string $label = '' ) :array {
		$route = [
			'handler' => $handler,
		];
		if ( $label !== '' ) {
			$route[ 'label' ] = $label;
		}
		return $route;
	}

	private static function activityBreadcrumbLabels() :array {
		$definitions = [];
		foreach ( self::investigateLandingSubjectDefinitions() as $subject ) {
			$subNav = $subject[ 'subnav_hint' ] ?? null;
			$label = $subject[ 'label' ] ?? '';
			if ( \is_string( $subNav ) && $subNav !== '' && \is_string( $label ) && $label !== '' ) {
				$definitions[ $subNav ] = $label;
			}
		}

		$definitions[ self::SUBNAV_LOGS ] = __( 'Activity Log', 'wp-simple-firewall' );
		$definitions[ self::SUBNAV_ACTIVITY_SESSIONS ] = __( 'User Sessions', 'wp-simple-firewall' );
		return $definitions;
	}

	private static function reportsSubNavDefinitions() :array {
		$definitions = [];
		$workspaceDefinitions = self::reportsWorkspaceDefinitions();
		foreach ( self::reportsRouteHandlers() as $subNav => $handler ) {
			$definitions[ $subNav ] = self::routeDefinition( $handler, $workspaceDefinitions[ $subNav ][ 'menu_title' ] ?? '' );
		}
		return $definitions;
	}
}
