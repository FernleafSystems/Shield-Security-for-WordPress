<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\{
	InstantAlerts,
	Reporting
};
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
	public const NAV_IPS = 'ips';
	public const SUBNAV_IPS_RULES = 'rules';
	public const NAV_LICENSE = 'license';
	public const SUBNAV_LICENSE_CHECK = 'check';
	public const NAV_OPTIONS_CONFIG = 'config';
	public const NAV_DASHBOARD = 'dashboard';
	public const SUBNAV_DASHBOARD_GRADES = 'grades';
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
	public const SUBNAV_TOOLS_DOCS = 'docs';
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
			function ( array $nav ) {
				if ( !isset( $nav[ 'parents' ] ) ) {
					$nav[ 'parents' ] = [];
				}
				if ( !\in_array( self::NAV_DASHBOARD, $nav[ 'parents' ] ) ) {
					$nav[ 'parents' ][] = self::NAV_DASHBOARD;
				}
				return $nav;
			},
			[
				self::NAV_ACTIVITY        => [
					'name'     => __( 'Activity', 'wp-simple-firewall' ),
					'sub_navs' => [
						self::SUBNAV_ACTIVITY_OVERVIEW  => [
							'handler' => PluginAdminPages\PageInvestigateLanding::class,
						],
						self::SUBNAV_ACTIVITY_BY_USER   => [
							'handler' => PluginAdminPages\PageInvestigateByUser::class,
						],
						self::SUBNAV_ACTIVITY_BY_IP     => [
							'handler' => PluginAdminPages\PageInvestigateByIp::class,
						],
						self::SUBNAV_ACTIVITY_BY_PLUGIN => [
							'handler' => PluginAdminPages\PageInvestigateByPlugin::class,
						],
						self::SUBNAV_ACTIVITY_BY_THEME  => [
							'handler' => PluginAdminPages\PageInvestigateByTheme::class,
						],
						self::SUBNAV_ACTIVITY_BY_CORE   => [
							'handler' => PluginAdminPages\PageInvestigateByCore::class,
						],
						self::SUBNAV_LOGS               => [
							'handler' => PluginAdminPages\PageActivityLogTable::class,
						],
					],
				],
				self::NAV_DASHBOARD       => [
					'name'     => __( 'Dashboard', 'wp-simple-firewall' ),
					'sub_navs' => [
						self::SUBNAV_DASHBOARD_OVERVIEW => [
							'handler' => PluginAdminPages\PageDashboardOverview::class,
						],
						self::SUBNAV_DASHBOARD_GRADES   => [
							'handler' => PluginAdminPages\PageDashboardMeters::class,
						],
					],
				],
				self::NAV_IPS             => [
					'name'     => __( 'IPs', 'wp-simple-firewall' ),
					'sub_navs' => [
						self::SUBNAV_IPS_RULES => [
							'handler' => PluginAdminPages\PageIpRulesTable::class,
						],
					],
				],
				self::NAV_LICENSE         => [
					'name'     => __( 'License', 'wp-simple-firewall' ),
					'sub_navs' => [
						self::SUBNAV_LICENSE_CHECK => [
							'handler' => PluginAdminPages\PageLicense::class,
						],
					],
				],
				self::NAV_REPORTS         => [
					'name'     => __( 'Reports', 'wp-simple-firewall' ),
					'sub_navs' => \array_map(
						fn( string $handler ) :array => [ 'handler' => $handler ],
						self::reportsRouteHandlers()
					),
				],
				self::NAV_RESTRICTED      => [
					'name'     => __( 'Restricted', 'wp-simple-firewall' ),
					'sub_navs' => [
						self::SUBNAV_INDEX => [
							'handler' => PluginAdminPages\PageSecurityAdminRestricted::class,
						],
					],
				],
				self::NAV_RULES           => [
					'name'     => __( 'Rules', 'wp-simple-firewall' ),
					'sub_navs' => [
						self::SUBNAV_RULES_MANAGE  => [
							'handler' => PluginAdminPages\PageRulesManage::class,
						],
						self::SUBNAV_RULES_BUILD   => [
							'handler' => PluginAdminPages\PageRulesBuild::class,
						],
						self::SUBNAV_RULES_SUMMARY => [
							'handler' => PluginAdminPages\PageRulesSummary::class,
						],
					],
				],
				self::NAV_SCANS           => [
					'name'     => __( 'Scans', 'wp-simple-firewall' ),
					'sub_navs' => [
						self::SUBNAV_SCANS_OVERVIEW => [
							'handler' => PluginAdminPages\PageActionsQueueLanding::class,
						],
						self::SUBNAV_SCANS_RESULTS  => [
							'handler' => PluginAdminPages\PageScansResults::class,
						],
						self::SUBNAV_SCANS_RUN      => [
							'handler' => PluginAdminPages\PageScansRun::class,
						],
					],
				],
				self::NAV_TOOLS           => [
					'name'     => __( 'Tools', 'wp-simple-firewall' ),
					'sub_navs' => [
						self::SUBNAV_TOOLS_BLOCKDOWN => [
							'handler' => PluginAdminPages\PageToolLockdown::class,
						],
						self::SUBNAV_TOOLS_SESSIONS  => [
							'handler' => PluginAdminPages\PageUserSessions::class,
						],
						self::SUBNAV_TOOLS_DEBUG     => [
							'handler' => PluginAdminPages\PageDebug::class,
						],
						self::SUBNAV_TOOLS_DOCS      => [
							'handler' => PluginAdminPages\PageDocs::class,
						],
						self::SUBNAV_TOOLS_IMPORT    => [
							'handler' => PluginAdminPages\PageImportExport::class,
						],
					],
				],
				self::NAV_TRAFFIC         => [
					'name'     => __( 'Traffic', 'wp-simple-firewall' ),
					'sub_navs' => [
						self::SUBNAV_LOGS => [
							'handler' => PluginAdminPages\PageTrafficLogTable::class,
						],
						self::SUBNAV_LIVE => [
							'handler' => PluginAdminPages\PageTrafficLogLive::class,
						],
					],
				],
				self::NAV_WIZARD          => [
					'name'     => __( 'Wizards', 'wp-simple-firewall' ),
					'sub_navs' => [
						self::SUBNAV_WIZARD_WELCOME => [
							'handler' => PluginAdminPages\PageMerlin::class,
						],
					],
				],
				self::NAV_ZONES           => [
					'name'     => __( 'Security Zones', 'wp-simple-firewall' ),
					'sub_navs' => \array_merge(
						[
							self::SUBNAV_ZONES_OVERVIEW => [
								'handler' => PluginAdminPages\PageConfigureLanding::class,
							],
						],
						\array_map(
							fn() :array => [ 'handler' => PluginAdminPages\PageDynamicLoad::class ],
							\array_flip( \array_keys( self::con()->comps->zones->enumZones() ) )
						)
					),
				],
				self::NAV_ZONE_COMPONENTS => [
					'name'     => __( 'Security Zones Config', 'wp-simple-firewall' ),
					'sub_navs' => \array_map(
						fn() :array => [ 'handler' => PluginAdminPages\PageZoneComponentConfig::class, ],
						\array_flip( \array_keys( self::con()->comps->zones->enumZoneComponents() ) )
					),
				],
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
		return $nav === self::NAV_DASHBOARD && $subNav === self::SUBNAV_DASHBOARD_GRADES
			? self::MODE_CONFIGURE
			: self::modeForNav( $nav );
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

	public static function investigateLandingSubjectDefinitions() :array {
		return [
			'users'       => [
				'label'              => __( 'Users', 'wp-simple-firewall' ),
				'description'        => __( 'Sessions, activity, and related IP addresses.', 'wp-simple-firewall' ),
				'icon_class'         => 'bi bi-people-fill',
				'panel_type'         => 'lookup_text',
				'subnav_hint'        => self::SUBNAV_ACTIVITY_BY_USER,
				'href_key'           => 'by_user',
				'input_key'          => 'user_lookup',
				'options_key'        => null,
				'panel_title'        => __( 'Investigate A User', 'wp-simple-firewall' ),
				'lookup_placeholder' => __( 'User ID, username, or email', 'wp-simple-firewall' ),
				'go_label'           => __( 'Investigate User', 'wp-simple-firewall' ),
				'is_enabled'         => true,
				'is_pro'             => false,
			],
			'ips'         => [
				'label'              => __( 'IP Addresses', 'wp-simple-firewall' ),
				'description'        => __( 'Reputation, bot signals, sessions, and traffic context.', 'wp-simple-firewall' ),
				'icon_class'         => 'bi bi-globe2',
				'panel_type'         => 'lookup_text',
				'subnav_hint'        => self::SUBNAV_ACTIVITY_BY_IP,
				'href_key'           => 'by_ip',
				'input_key'          => 'analyse_ip',
				'options_key'        => null,
				'panel_title'        => __( 'Investigate An IP Address', 'wp-simple-firewall' ),
				'lookup_placeholder' => __( 'IP address', 'wp-simple-firewall' ),
				'go_label'           => __( 'Investigate IP', 'wp-simple-firewall' ),
				'is_enabled'         => true,
				'is_pro'             => false,
			],
			'plugins'     => [
				'label'              => __( 'Plugins', 'wp-simple-firewall' ),
				'description'        => __( 'File status, vulnerabilities, and plugin activity.', 'wp-simple-firewall' ),
				'icon_class'         => 'bi bi-puzzle-fill',
				'panel_type'         => 'lookup_select',
				'subnav_hint'        => self::SUBNAV_ACTIVITY_BY_PLUGIN,
				'href_key'           => 'by_plugin',
				'input_key'          => 'plugin_slug',
				'options_key'        => 'plugin_options',
				'panel_title'        => __( 'Investigate A Plugin', 'wp-simple-firewall' ),
				'lookup_placeholder' => __( 'Select a plugin', 'wp-simple-firewall' ),
				'go_label'           => __( 'Investigate Plugin', 'wp-simple-firewall' ),
				'is_enabled'         => true,
				'is_pro'             => false,
			],
			'themes'      => [
				'label'              => __( 'Themes', 'wp-simple-firewall' ),
				'description'        => __( 'File status, vulnerabilities, and theme activity.', 'wp-simple-firewall' ),
				'icon_class'         => 'bi bi-palette-fill',
				'panel_type'         => 'lookup_select',
				'subnav_hint'        => self::SUBNAV_ACTIVITY_BY_THEME,
				'href_key'           => 'by_theme',
				'input_key'          => 'theme_slug',
				'options_key'        => 'theme_options',
				'panel_title'        => __( 'Investigate A Theme', 'wp-simple-firewall' ),
				'lookup_placeholder' => __( 'Select a theme', 'wp-simple-firewall' ),
				'go_label'           => __( 'Investigate Theme', 'wp-simple-firewall' ),
				'is_enabled'         => true,
				'is_pro'             => false,
			],
			'wordpress'   => [
				'label'              => __( 'WordPress Core', 'wp-simple-firewall' ),
				'description'        => __( 'Core file integrity and platform update state.', 'wp-simple-firewall' ),
				'icon_class'         => 'bi bi-wordpress',
				'panel_type'         => 'direct_link',
				'subnav_hint'        => self::SUBNAV_ACTIVITY_BY_CORE,
				'href_key'           => 'by_core',
				'input_key'          => null,
				'options_key'        => null,
				'panel_title'        => __( 'WordPress Core Integrity', 'wp-simple-firewall' ),
				'lookup_placeholder' => '',
				'go_label'           => __( 'View Core Status', 'wp-simple-firewall' ),
				'is_enabled'         => true,
				'is_pro'             => false,
			],
			'requests'    => [
				'label'              => __( 'HTTP Requests', 'wp-simple-firewall' ),
				'description'        => __( 'Browse full HTTP request log data.', 'wp-simple-firewall' ),
				'icon_class'         => 'bi bi-arrow-left-right',
				'panel_type'         => 'direct_link',
				'subnav_hint'        => null,
				'href_key'           => 'traffic_log',
				'input_key'          => null,
				'options_key'        => null,
				'panel_title'        => __( 'HTTP Request Log', 'wp-simple-firewall' ),
				'lookup_placeholder' => '',
				'go_label'           => __( 'Open Request Log', 'wp-simple-firewall' ),
				'is_enabled'         => true,
				'is_pro'             => false,
			],
			'activity'    => [
				'label'              => __( 'Activity Log', 'wp-simple-firewall' ),
				'description'        => __( 'Browse full site activity events.', 'wp-simple-firewall' ),
				'icon_class'         => 'bi bi-journal-text',
				'panel_type'         => 'direct_link',
				'subnav_hint'        => null,
				'href_key'           => 'activity_log',
				'input_key'          => null,
				'options_key'        => null,
				'panel_title'        => __( 'Activity Log', 'wp-simple-firewall' ),
				'lookup_placeholder' => '',
				'go_label'           => __( 'Open Activity Log', 'wp-simple-firewall' ),
				'is_enabled'         => true,
				'is_pro'             => false,
			],
			'woocommerce' => [
				'label'              => __( 'WooCommerce', 'wp-simple-firewall' ),
				'description'        => __( 'Orders and customer security context.', 'wp-simple-firewall' ),
				'icon_class'         => 'bi bi-cart3',
				'panel_type'         => 'disabled',
				'subnav_hint'        => null,
				'href_key'           => '',
				'input_key'          => null,
				'options_key'        => null,
				'panel_title'        => '',
				'lookup_placeholder' => '',
				'go_label'           => '',
				'is_enabled'         => false,
				'is_pro'             => true,
			],
		];
	}

	public static function investigateSubNavCrumbLabel( string $subNav ) :string {
		$subNavDefinition = self::investigateSubNavDefinition( $subNav );
		return \is_string( $subNavDefinition[ 'label' ] ?? null ) ? $subNavDefinition[ 'label' ] : '';
	}

	public static function investigateSubNavDefinitions() :array {
		$definitions = [];
		foreach ( self::investigateLandingSubjectDefinitions() as $subject ) {
			$subNav = (string)( $subject[ 'subnav_hint' ] ?? '' );
			$label = (string)( $subject[ 'label' ] ?? '' );
			if ( !empty( $subNav ) && !empty( $label ) ) {
				$definitions[ $subNav ] = [ 'label' => $label ];
			}
		}

		$definitions[ self::SUBNAV_LOGS ] = [ 'label' => __( 'Activity Log', 'wp-simple-firewall' ) ];
		return $definitions;
	}

	public static function investigateSubNavDefinition( string $subNav ) :array {
		return self::investigateSubNavDefinitions()[ $subNav ] ?? [];
	}

	public static function breadcrumbSubNavDefinition( string $nav, string $subNav ) :array {
		if ( $nav === self::NAV_ACTIVITY ) {
			return self::investigateSubNavDefinition( $subNav );
		}

		if ( $nav === self::NAV_REPORTS ) {
			$workspace = self::reportsWorkspaceDefinitions()[ $subNav ] ?? [];
			$menuTitle = $workspace[ 'menu_title' ] ?? null;
			if ( \is_string( $menuTitle ) && $menuTitle !== '' ) {
				return [ 'label' => $menuTitle ];
			}
		}

		$definitions = [
			self::NAV_DASHBOARD => [
				self::SUBNAV_DASHBOARD_GRADES => [ 'label' => __( 'Security Grades', 'wp-simple-firewall' ) ],
			],
			self::NAV_IPS       => [
				self::SUBNAV_IPS_RULES => [ 'label' => __( 'Bots & IP Rules', 'wp-simple-firewall' ) ],
			],
			self::NAV_SCANS     => [
				self::SUBNAV_SCANS_RESULTS => [ 'label' => __( 'Scan Results', 'wp-simple-firewall' ) ],
				self::SUBNAV_SCANS_RUN     => [ 'label' => __( 'Run Scan', 'wp-simple-firewall' ) ],
			],
			self::NAV_TRAFFIC   => [
				self::SUBNAV_LOGS => [ 'label' => __( 'HTTP Request Log', 'wp-simple-firewall' ) ],
				self::SUBNAV_LIVE => [ 'label' => __( 'Live HTTP Log', 'wp-simple-firewall' ) ],
			],
		];

		return $definitions[ $nav ][ $subNav ] ?? [];
	}

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
			],
			self::SUBNAV_REPORTS_CHARTS   => [
				'menu_title'         => __( 'Charts & Trends', 'wp-simple-firewall' ),
				'landing_cta'        => __( 'Open Charts & Trends', 'wp-simple-firewall' ),
				'page_title'         => __( 'Charts & Trends', 'wp-simple-firewall' ),
				'page_subtitle'      => __( 'Review recent security trend metrics.', 'wp-simple-firewall' ),
				'content_key'        => 'summary_charts',
				'render_action'      => Reports\ChartsSummary::class,
				'show_create_action' => false,
			],
			self::SUBNAV_REPORTS_SETTINGS => [
				'menu_title'         => __( 'Alert Settings', 'wp-simple-firewall' ),
				'landing_cta'        => __( 'Open Alert Settings', 'wp-simple-firewall' ),
				'page_title'         => __( 'Alert Settings', 'wp-simple-firewall' ),
				'page_subtitle'      => __( 'Manage instant alerts and report delivery settings.', 'wp-simple-firewall' ),
				'content_key'        => 'alerts_settings',
				'render_action'      => OptionsFormFor::class,
				'show_create_action' => false,
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
}
