<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\{
	InstantAlerts,
	PluginGeneral,
	Reporting,
	RequestLogging
};
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-import-type TileDefinition from \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureZoneTilesBuilder
 */
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
	public const SUBNAV_REPORTS_CHARTS = 'charts';
	public const SUBNAV_REPORTS_SETTINGS = 'settings';
	public const NAV_RULES = 'rules';
	public const SUBNAV_RULES_MANAGE = 'manage';
	public const SUBNAV_RULES_BUILD = 'build';
	public const SUBNAV_RULES_SUMMARY = 'summary';
	public const NAV_SCANS = 'scans';
	public const SUBNAV_SCANS_OVERVIEW = 'overview';
	/** @deprecated 22.0. legacy redirect target only */
	public const SUBNAV_SCANS_STATE = 'state';
	/** @deprecated 22.0. legacy redirect target only */
	public const SUBNAV_SCANS_HISTORY = 'history';
	/** @deprecated 22.0. legacy redirect target only */
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
		return \key( PluginNavs::GetNavHierarchy()[ $nav ][ 'sub_navs' ] );
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
	 *   context_summary:string,
	 *   context_focus:string,
	 *   context_next_step:string,
	 *   context_badge:string,
	 *   render_action:string,
	 *   render_nav:string,
	 *   render_subnav:string,
	 *   lookup_key:string,
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
				'context_summary'   => __( 'Select a user to inspect matching sessions, activity, and request history.', 'wp-simple-firewall' ),
				'context_focus'     => __( 'Sessions, activity logs, requests, and related IP addresses for one user.', 'wp-simple-firewall' ),
				'context_next_step' => __( 'Choose a user, then switch between overview, sessions, activity, requests, and related IPs.', 'wp-simple-firewall' ),
				'context_badge'     => __( 'User activity', 'wp-simple-firewall' ),
				'render_action' => PluginAdminPages\InvestigateByUserPanelBody::class,
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
				'context_summary'   => __( 'Select an IP address to inspect matching sessions, activity, and request history.', 'wp-simple-firewall' ),
				'context_focus'     => __( 'Requests, activity logs, and sessions tied to one IP address.', 'wp-simple-firewall' ),
				'context_next_step' => __( 'Choose an IP address, then switch between sessions, activity, and recent traffic.', 'wp-simple-firewall' ),
				'context_badge'     => __( 'IP activity', 'wp-simple-firewall' ),
				'render_action' => PluginAdminPages\InvestigateByIpPanelBody::class,
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
				'context_summary'   => __( 'Select a plugin to inspect integrity, vulnerabilities, and related activity.', 'wp-simple-firewall' ),
				'context_focus'     => __( 'File status, vulnerability exposure, and activity for one plugin.', 'wp-simple-firewall' ),
				'context_next_step' => __( 'Choose a plugin, then switch between overview, file status, vulnerabilities, and activity.', 'wp-simple-firewall' ),
				'context_badge'     => __( 'Plugin activity', 'wp-simple-firewall' ),
				'render_action' => PluginAdminPages\InvestigateByPluginPanelBody::class,
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
				'context_summary'   => __( 'Select a theme to inspect integrity, vulnerabilities, and related activity.', 'wp-simple-firewall' ),
				'context_focus'     => __( 'File status, vulnerability exposure, and activity for one theme.', 'wp-simple-firewall' ),
				'context_next_step' => __( 'Choose a theme, then switch between overview, file status, vulnerabilities, and activity.', 'wp-simple-firewall' ),
				'context_badge'     => __( 'Theme activity', 'wp-simple-firewall' ),
				'render_action' => PluginAdminPages\InvestigateByThemePanelBody::class,
				'render_nav'    => self::NAV_ACTIVITY,
				'render_subnav' => self::SUBNAV_ACTIVITY_BY_THEME,
				'lookup_key'    => 'theme_slug',
				'is_enabled'    => true,
				'is_pro'        => false,
			],
			'core'                 => [
				'label'         => __( 'WordPress Core', 'wp-simple-firewall' ),
				'icon_class'    => 'bi bi-wordpress',
				'status'        => 'info',
				'stat_text'     => __( 'Integrity and activity', 'wp-simple-firewall' ),
				'subnav_hint'   => self::SUBNAV_ACTIVITY_BY_CORE,
				'context_summary'   => __( 'Review WordPress core integrity and platform activity.', 'wp-simple-firewall' ),
				'context_focus'     => __( 'Core file status and activity tied to this WordPress installation.', 'wp-simple-firewall' ),
				'context_next_step' => __( 'Switch between overview, file status, and activity to review WordPress Core.', 'wp-simple-firewall' ),
				'context_badge'     => __( 'Core activity', 'wp-simple-firewall' ),
				'render_action' => PluginAdminPages\InvestigateByCorePanelBody::class,
				'render_nav'    => self::NAV_ACTIVITY,
				'render_subnav' => self::SUBNAV_ACTIVITY_BY_CORE,
				'lookup_key'    => '',
				'is_enabled'    => true,
				'is_pro'        => false,
			],
			'live_traffic'         => [
				'label'         => __( 'Live Traffic', 'wp-simple-firewall' ),
				'icon_class'    => 'bi bi-lightning',
				'status'        => 'info',
				'stat_text'     => __( 'Live request stream', 'wp-simple-firewall' ),
				'subnav_hint'   => null,
				'context_summary'   => __( 'Watch recent HTTP requests as they reach the site.', 'wp-simple-firewall' ),
				'context_focus'     => __( 'Live request stream for current site traffic.', 'wp-simple-firewall' ),
				'context_next_step' => __( 'Review the latest entries and pivot into deeper investigations where useful.', 'wp-simple-firewall' ),
				'context_badge'     => __( 'Live traffic', 'wp-simple-firewall' ),
				'render_action' => PluginAdminPages\TrafficLogLivePanelBody::class,
				'render_nav'    => self::NAV_TRAFFIC,
				'render_subnav' => self::SUBNAV_LIVE,
				'lookup_key'    => '',
				'is_enabled'    => true,
				'is_pro'        => false,
			],
			'premium_integrations' => [
				'label'         => __( 'Premium Integrations', 'wp-simple-firewall' ),
				'icon_class'    => 'bi bi-stars',
				'status'        => 'neutral',
				'stat_text'     => __( 'Coming soon', 'wp-simple-firewall' ),
				'subnav_hint'   => null,
				'context_summary'   => __( 'Premium investigation integrations will appear here when available.', 'wp-simple-firewall' ),
				'context_focus'     => __( 'Additional investigation sources for premium operators.', 'wp-simple-firewall' ),
				'context_next_step' => __( 'Use the available investigation subjects while premium integrations are unavailable.', 'wp-simple-firewall' ),
				'context_badge'     => __( 'Coming soon', 'wp-simple-firewall' ),
				'render_action' => '',
				'render_nav'    => '',
				'render_subnav' => '',
				'lookup_key'    => '',
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

	/**
	 * @return list<TileDefinition>
	 */
	public static function configureLandingTileDefinitions() :array {
		return [
			[
				'key'      => 'secadmin',
				'label'    => __( 'Security Admin', 'wp-simple-firewall' ),
				'icon'     => 'shield-lock',
				'summary'  => __( 'An additional WP Admin Security Layer that further protects core WordPress settings.', 'wp-simple-firewall' ),
				'zone_slug' => Zone\Secadmin::Slug(),
			],
			[
				'key'      => 'firewall',
				'label'    => __( 'Firewall', 'wp-simple-firewall' ),
				'icon'     => 'fire',
				'summary'  => __( 'The Firewall is the crucial perimeter defense of your WordPress site.', 'wp-simple-firewall' ),
				'zone_slug' => Zone\Firewall::Slug(),
			],
			[
				'key'      => 'ips',
				'label'    => __( 'Bots and IPs', 'wp-simple-firewall' ),
				'icon'     => 'robot',
				'summary'  => __( 'Beat the bots by auto-blocking IP addresses of repeat abusers.', 'wp-simple-firewall' ),
				'zone_slug' => Zone\Ips::Slug(),
			],
			[
				'key'      => 'scans',
				'label'    => __( 'Scans', 'wp-simple-firewall' ),
				'icon'     => 'bug',
				'summary'  => __( 'Regular scanning quickly identifies compromise and prevents exploitation of vulnerabilities.', 'wp-simple-firewall' ),
				'zone_slug' => Zone\Scans::Slug(),
			],
			[
				'key'      => 'login',
				'label'    => __( 'Login', 'wp-simple-firewall' ),
				'icon'     => 'person-lock',
				'summary'  => __( 'Protect user logins with 2FA and critical session hijacking prevention.', 'wp-simple-firewall' ),
				'zone_slug' => Zone\Login::Slug(),
			],
			[
				'key'      => 'users',
				'label'    => __( 'Users', 'wp-simple-firewall' ),
				'icon'     => 'people',
				'summary'  => __( 'Protection for user accounts.', 'wp-simple-firewall' ),
				'zone_slug' => Zone\Users::Slug(),
			],
			[
				'key'      => 'spam',
				'label'    => __( 'SPAM', 'wp-simple-firewall' ),
				'icon'     => 'chat-dots',
				'summary'  => __( 'Block WordPress Comment SPAM and Contact Form SPAM.', 'wp-simple-firewall' ),
				'zone_slug' => Zone\Spam::Slug(),
			],
			[
				'key'      => 'headers',
				'label'    => __( 'HTTP Headers', 'wp-simple-firewall' ),
				'icon'     => 'file-earmark-lock',
				'summary'  => __( 'HTTP Headers provide protection for your site visitors.', 'wp-simple-firewall' ),
				'zone_slug' => Zone\Headers::Slug(),
			],
			[
				'key'                => 'general',
				'label'              => __( 'General', 'wp-simple-firewall' ),
				'icon'               => 'sliders',
				'summary'            => __( 'Configure site-wide Shield Security controls.', 'wp-simple-firewall' ),
				'component_slugs'    => [
					PluginGeneral::Slug(),
					RequestLogging::Slug(),
				],
				'include_in_posture' => false,
				'force_neutral'      => true,
				'stat_line'          => __( 'General settings', 'wp-simple-firewall' ),
			],
			[
				'key'                => 'reports_alerts',
				'label'              => __( 'Reports & Alerts', 'wp-simple-firewall' ),
				'icon'               => 'bell',
				'summary'            => __( 'Manage Reports and Alerts that surface security activity to administrators.', 'wp-simple-firewall' ),
				'component_slugs'    => [
					InstantAlerts::Slug(),
					Reporting::Slug(),
				],
				'include_in_posture' => false,
				'force_neutral'      => true,
				'stat_line'          => __( 'Report and alert settings', 'wp-simple-firewall' ),
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
	 * @return array<string,array{
	 *   slug:string,
	 *   label:string,
	 *   icon:string,
	 *   summary_keys:list<string>
	 * }>
	 */
	public static function actionsLandingScanDefinitions() :array {
		return [
			'wordpress'       => [
				'slug'            => 'wordpress',
				'label'           => __( 'WordPress Files', 'wp-simple-firewall' ),
				'icon'            => 'wordpress',
				'summary_keys'    => [ 'wp_files' ],
			],
			'plugins'         => [
				'slug'            => 'plugins',
				'label'           => __( 'Plugin Files', 'wp-simple-firewall' ),
				'icon'            => 'plug',
				'summary_keys'    => [ 'plugin_files', 'plugin_files_ignored' ],
			],
			'themes'          => [
				'slug'            => 'themes',
				'label'           => __( 'Theme Files', 'wp-simple-firewall' ),
				'icon'            => 'brush',
				'summary_keys'    => [ 'theme_files' ],
			],
			'vulnerabilities' => [
				'slug'            => 'vulnerabilities',
				'label'           => __( 'Vulnerabilities', 'wp-simple-firewall' ),
				'icon'            => 'shield-exclamation',
				'summary_keys'    => [ 'vulnerable_assets', 'abandoned' ],
			],
			'malware'         => [
				'slug'            => 'malware',
				'label'           => __( 'Malware', 'wp-simple-firewall' ),
				'icon'            => 'bug',
				'summary_keys'    => [ 'malware' ],
			],
			'file_locker'     => [
				'slug'            => 'file_locker',
				'label'           => __( 'File Locker', 'wp-simple-firewall' ),
				'icon'            => 'file-lock2',
				'summary_keys'    => [ 'file_locker' ],
			],
		];
	}

	/**
	 * Queue-local scan definitions split abandoned assets from vulnerabilities while
	 * preserving the classic landing definitions for non-queue consumers.
	 *
	 * @return array<string,array{
	 *   slug:string,
	 *   label:string,
	 *   icon:string,
	 *   summary_keys:list<string>
	 * }>
	 */
	public static function actionsQueueScanDefinitions() :array {
		$definitions = self::actionsLandingScanDefinitions();
		$definitions[ 'vulnerabilities' ][ 'summary_keys' ] = [ 'vulnerable_assets' ];

		return \array_merge(
			\array_slice( $definitions, 0, 4, true ),
			[
				'abandoned' => [
					'slug'         => 'abandoned',
					'label'        => __( 'Abandoned Assets', 'wp-simple-firewall' ),
					'icon'         => 'archive',
					'summary_keys' => [ 'abandoned' ],
				],
			],
			\array_slice( $definitions, 4, null, true )
		);
	}

	/**
	 * @return array{
	 *   slug:string,
	 *   label:string,
	 *   icon:string,
	 *   summary_keys:list<string>
	 * }|null
	 */
	public static function actionsLandingScanDefinitionForSummaryKey( string $summaryKey ) :?array {
		return self::scanDefinitionForSummaryKey( self::actionsLandingScanDefinitions(), $summaryKey );
	}

	/**
	 * @return array{
	 *   slug:string,
	 *   label:string,
	 *   icon:string,
	 *   summary_keys:list<string>
	 * }|null
	 */
	public static function actionsQueueScanDefinitionForSummaryKey( string $summaryKey ) :?array {
		return self::scanDefinitionForSummaryKey( self::actionsQueueScanDefinitions(), $summaryKey );
	}

	/**
	 * @param array<string,array{
	 *   slug:string,
	 *   label:string,
	 *   icon:string,
	 *   summary_keys:list<string>
	 * }> $definitions
	 * @return array{
	 *   slug:string,
	 *   label:string,
	 *   icon:string,
	 *   summary_keys:list<string>
	 * }|null
	 */
	private static function scanDefinitionForSummaryKey( array $definitions, string $summaryKey ) :?array {
		foreach ( $definitions as $definition ) {
			if ( \in_array( $summaryKey, $definition[ 'summary_keys' ], true ) ) {
				return $definition;
			}
		}

		return null;
	}

	public static function actionsLandingScanRowIcon( string $summaryKey ) :string {
		return ( new ActionsQueueItemIcons() )->iconForKey( $summaryKey );
	}

	public static function actionsLandingScanRailIconClass( string $scanKey ) :string {
		return ( new ActionsQueueItemIcons() )->iconClassForScanKey( $scanKey );
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   zone:string,
	 *   component_class:class-string<Component\Base>,
	 *   availability_strategy:string,
	 *   drill_bucket:'critical'|'review'
	 * }>
	 */
	public static function actionsLandingAssessmentDefinitions() :array {
		return [
			[
				'key'                   => 'wp_files',
				'zone'                  => 'scans',
				'component_class'       => Component\ScanResultsWcf::class,
				'availability_strategy' => 'scan_afs_core_enabled',
				'drill_bucket'          => 'critical',
			],
			[
				'key'                   => 'plugin_files',
				'zone'                  => 'scans',
				'component_class'       => Component\ScanResultsPluginFiles::class,
				'availability_strategy' => 'scan_afs_plugins_enabled',
				'drill_bucket'          => 'critical',
			],
			[
				'key'                   => 'theme_files',
				'zone'                  => 'scans',
				'component_class'       => Component\ScanResultsThemeFiles::class,
				'availability_strategy' => 'scan_afs_themes_enabled',
				'drill_bucket'          => 'critical',
			],
			[
				'key'                   => 'malware',
				'zone'                  => 'scans',
				'component_class'       => Component\ScanResultsMal::class,
				'availability_strategy' => 'scan_malware_enabled',
				'drill_bucket'          => 'critical',
			],
			[
				'key'                   => 'file_locker',
				'zone'                  => 'scans',
				'component_class'       => Component\ScanResultsFileLocker::class,
				'availability_strategy' => 'scan_file_locker_enabled',
				'drill_bucket'          => 'critical',
			],
			[
				'key'                   => 'vulnerable_assets',
				'zone'                  => 'scans',
				'component_class'       => Component\ScanResultsWpv::class,
				'availability_strategy' => 'scan_wpv_enabled',
				'drill_bucket'          => 'critical',
			],
			[
				'key'                   => 'abandoned',
				'zone'                  => 'scans',
				'component_class'       => Component\ScanResultsApc::class,
				'availability_strategy' => 'scan_apc_enabled',
				'drill_bucket'          => 'critical',
			],
			[
				'key'                   => 'default_admin_user',
				'zone'                  => 'maintenance',
				'component_class'       => Component\DefaultAdminUser::class,
				'availability_strategy' => 'always',
				'drill_bucket'          => 'review',
			],
			[
				'key'                   => 'wp_updates',
				'zone'                  => 'maintenance',
				'component_class'       => Component\WpUpdates::class,
				'availability_strategy' => 'always',
				'drill_bucket'          => 'review',
			],
			[
				'key'                   => 'wp_plugins_updates',
				'zone'                  => 'maintenance',
				'component_class'       => Component\WpPluginsUpdates::class,
				'availability_strategy' => 'always',
				'drill_bucket'          => 'review',
			],
			[
				'key'                   => 'wp_themes_updates',
				'zone'                  => 'maintenance',
				'component_class'       => Component\WpThemesUpdates::class,
				'availability_strategy' => 'always',
				'drill_bucket'          => 'review',
			],
			[
				'key'                   => 'wp_plugins_inactive',
				'zone'                  => 'maintenance',
				'component_class'       => Component\WpPluginsInactive::class,
				'availability_strategy' => 'always',
				'drill_bucket'          => 'review',
			],
			[
				'key'                   => 'wp_themes_inactive',
				'zone'                  => 'maintenance',
				'component_class'       => Component\WpThemesInactive::class,
				'availability_strategy' => 'always',
				'drill_bucket'          => 'review',
			],
			[
				'key'                   => 'system_ssl_certificate',
				'zone'                  => 'maintenance',
				'component_class'       => Component\SystemSslCertificate::class,
				'availability_strategy' => 'always',
				'drill_bucket'          => 'review',
			],
			[
				'key'                   => 'system_php_version',
				'zone'                  => 'maintenance',
				'component_class'       => Component\SystemPhpVersion::class,
				'availability_strategy' => 'always',
				'drill_bucket'          => 'review',
			],
			[
				'key'                   => 'wp_db_password',
				'zone'                  => 'maintenance',
				'component_class'       => Component\WpDbPassword::class,
				'availability_strategy' => 'always',
				'drill_bucket'          => 'review',
			],
			[
				'key'                   => 'system_lib_openssl',
				'zone'                  => 'maintenance',
				'component_class'       => Component\SystemLibOpenssl::class,
				'availability_strategy' => 'always',
				'drill_bucket'          => 'review',
			],
		];
	}

	/**
	 * @return array<string,array{
	 *   menu_title:string,
	 *   page_title:string,
	 *   page_subtitle:string,
	 *   content_key:string,
	 *   render_action:string,
	 *   show_create_action:bool,
	 *   config_zone_component_slugs:list<string>
	 * }>
	 */
	public static function reportsWorkspaceDefinitions() :array {
		return [
			self::SUBNAV_REPORTS_LIST     => [
				'menu_title'         => __( 'Security Reports', 'wp-simple-firewall' ),
				'page_title'         => __( 'View & Create', 'wp-simple-firewall' ),
				'page_subtitle'      => __( 'View and create new security reports.', 'wp-simple-firewall' ),
				'content_key'        => 'create_report',
				'render_action'      => Reports\PageReportsView::class,
				'show_create_action' => true,
				'config_zone_component_slugs' => [],
			],
			self::SUBNAV_REPORTS_SETTINGS => [
				'menu_title'         => __( 'Reporting & Alerts Configuration', 'wp-simple-firewall' ),
				'page_title'         => __( 'Reporting & Alerts Configuration', 'wp-simple-firewall' ),
				'page_subtitle'      => '',
				'content_key'        => 'reporting_alerts_configuration',
				'render_action'      => OptionsFormFor::class,
				'show_create_action' => false,
				'config_zone_component_slugs' => self::reportsSettingsZoneComponentSlugs(),
			],
			self::SUBNAV_REPORTS_CHARTS   => [
				'menu_title'         => __( 'Charts & Trends', 'wp-simple-firewall' ),
				'page_title'         => __( 'Charts & Trends', 'wp-simple-firewall' ),
				'page_subtitle'      => '',
				'content_key'        => 'charts_trends',
				'render_action'      => Reports\ChartsTrends::class,
				'show_create_action' => false,
				'config_zone_component_slugs' => [],
			],
		];
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

	public static function reportsSettingsZoneComponentSlugs() :array {
		return [
			InstantAlerts::Slug(),
			Reporting::Slug(),
		];
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
				self::SUBNAV_ACTIVITY_BY_USER   => self::routeDefinition( PluginAdminPages\PageInvestigateLanding::class, $labels[ self::SUBNAV_ACTIVITY_BY_USER ] ?? '' ),
				self::SUBNAV_ACTIVITY_BY_IP     => self::routeDefinition( PluginAdminPages\PageInvestigateLanding::class, $labels[ self::SUBNAV_ACTIVITY_BY_IP ] ?? '' ),
				self::SUBNAV_ACTIVITY_BY_PLUGIN => self::routeDefinition( PluginAdminPages\PageInvestigateLanding::class, $labels[ self::SUBNAV_ACTIVITY_BY_PLUGIN ] ?? '' ),
				self::SUBNAV_ACTIVITY_BY_THEME  => self::routeDefinition( PluginAdminPages\PageInvestigateLanding::class, $labels[ self::SUBNAV_ACTIVITY_BY_THEME ] ?? '' ),
				self::SUBNAV_ACTIVITY_BY_CORE   => self::routeDefinition( PluginAdminPages\PageInvestigateLanding::class, $labels[ self::SUBNAV_ACTIVITY_BY_CORE ] ?? '' ),
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
			'sub_navs' => [
				self::SUBNAV_ZONES_OVERVIEW => self::routeDefinition( PluginAdminPages\PageConfigureLanding::class ),
			],
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
