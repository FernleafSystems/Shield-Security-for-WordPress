<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
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
						self::SUBNAV_ACTIVITY_OVERVIEW => [
							'handler' => PluginAdminPages\PageInvestigateLanding::class,
						],
						self::SUBNAV_ACTIVITY_BY_USER  => [
							'handler' => PluginAdminPages\PageInvestigateByUser::class,
						],
						self::SUBNAV_ACTIVITY_BY_IP    => [
							'handler' => PluginAdminPages\PageInvestigateByIp::class,
						],
						self::SUBNAV_ACTIVITY_BY_PLUGIN => [
							'handler' => PluginAdminPages\PageInvestigateByPlugin::class,
						],
						self::SUBNAV_ACTIVITY_BY_THEME => [
							'handler' => PluginAdminPages\PageInvestigateByTheme::class,
						],
						self::SUBNAV_ACTIVITY_BY_CORE => [
							'handler' => PluginAdminPages\PageInvestigateByCore::class,
						],
						self::SUBNAV_LOGS              => [
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
					'sub_navs' => [
						self::SUBNAV_REPORTS_OVERVIEW => [
							'handler' => PluginAdminPages\PageReportsLanding::class,
						],
						self::SUBNAV_REPORTS_LIST     => [
							'handler' => PluginAdminPages\PageReports::class,
						],
						self::SUBNAV_REPORTS_CHARTS   => [
							'handler' => PluginAdminPages\PageReports::class,
						],
						self::SUBNAV_REPORTS_SETTINGS => [
							'handler' => PluginAdminPages\PageReports::class,
						],
					],
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
						self::SUBNAV_SCANS_RESULTS => [
							'handler' => PluginAdminPages\PageScansResults::class,
						],
						self::SUBNAV_SCANS_RUN     => [
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
							function () {
								return [
									'handler' => PluginAdminPages\PageDynamicLoad::class,
								];
							},
							\array_flip( \array_keys( self::con()->comps->zones->enumZones() ) )
						)
					),
				],
				self::NAV_ZONE_COMPONENTS => [
					'name'     => __( 'Security Zones Config', 'wp-simple-firewall' ),
					'sub_navs' => \array_map(
						function () {
							return [
								'handler' => PluginAdminPages\PageZoneComponentConfig::class,
							];
						},
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

	private static function sanitizeOperatorMode( string $mode ) :string {
		$mode = \strtolower( \trim( $mode ) );
		return \in_array( $mode, self::allOperatorModes(), true ) ? $mode : '';
	}
}
