<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class PluginNavs {

	use PluginControllerConsumer;

	public const FIELD_NAV = 'nav';
	public const FIELD_SUBNAV = 'nav_sub';
	public const NAV_ACTIVITY = 'activity';
	public const SUBNAV_ACTIVITY_LOG = 'log';
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
	public const SUBNAV_REPORTS_LIST = 'list';
	public const NAV_RULES = 'rules';
	public const SUBNAV_RULES_SUMMARY = 'summary';
	public const NAV_SCANS = 'scans';
	public const SUBNAV_SCANS_RESULTS = 'results';
	public const SUBNAV_SCANS_RUN = 'run';
	public const NAV_STATS = 'stats';
	public const NAV_TRAFFIC = 'traffic';
	public const SUBNAV_TRAFFIC_LOG = 'log';
	public const SUBNAV_LIVE = 'live';
	public const NAV_TOOLS = 'tools';
	public const SUBNAV_TOOLS_DEBUG = 'debug';
	public const SUBNAV_TOOLS_IMPORT = 'importexport';
	public const SUBNAV_TOOLS_DOCS = 'docs';
	public const SUBNAV_TOOLS_SESSIONS = 'sessions';
	public const NAV_WIZARD = 'merlin';
	public const SUBNAV_WIZARD_WELCOME = 'welcome';
	public const SUBNAV_INDEX = 'index'; /* special case used only to indicate pick first in subnav list, for now */
	public const SUBNAV_LOGS = 'logs';
	/** @deprecated 18.3 */
	public const NAV_OVERVIEW = 'overview';
	public const NAV_IMPORT_EXPORT = 'importexport';
	public const NAV_SCANS_RESULTS = 'scans_results';
	public const NAV_SCANS_RUN = 'scans_run';
	public const NAV_USER_SESSIONS = 'users';
	public const NAV_RULES_VIEW = 'rules';
	public const SUBNAV_REPORTS_CHARTS = 'charts';
	public const SUBNAV_REPORTS_STATS = 'stats';

	public static function GetNav() :string {
		return (string)Services::Request()->query( self::FIELD_NAV );
	}

	public static function GetSubNav() :string {
		return (string)Services::Request()->query( self::FIELD_SUBNAV );
	}

	public static function GetAllNavs() :array {
		$cons = ( new \ReflectionClass( __CLASS__ ) )->getConstants();
		return \array_intersect_key( $cons, \array_flip( \array_filter(
			\array_keys( $cons ),
			function ( string $nav ) {
				return \strpos( $nav, 'NAV_' ) === 0;
			}
		) ) );
	}

	/**
	 * Handle special case for Config, so we ensure plugin general config is always default.
	 */
	public static function GetDefaultSubNavForNav( string $nav ) :string {
		return $nav === self::NAV_OPTIONS_CONFIG ? ModCon::SLUG : \key( PluginNavs::GetNavHierarchy()[ $nav ][ 'sub_navs' ] );
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
				self::NAV_ACTIVITY       => [
					'name'     => __( 'Activity', 'wp-simple-firewall' ),
					'sub_navs' => [
						self::SUBNAV_ACTIVITY_LOG => [
							'handler' => PluginAdminPages\PageActivityLogTable::class,
						],
						self::SUBNAV_LOGS => [
							'handler' => PluginAdminPages\PageActivityLogTable::class,
						],
					],
				],
				self::NAV_DASHBOARD      => [
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
				self::NAV_IPS            => [
					'name'     => __( 'IPs', 'wp-simple-firewall' ),
					'sub_navs' => [
						self::SUBNAV_IPS_RULES => [
							'handler' => PluginAdminPages\PageIpRulesTable::class,
						],
					],
				],
				self::NAV_LICENSE        => [
					'name'     => __( 'License', 'wp-simple-firewall' ),
					'sub_navs' => [
						self::SUBNAV_LICENSE_CHECK => [
							'handler' => PluginAdminPages\PageLicense::class,
						],
					],
				],
				self::NAV_OPTIONS_CONFIG => [
					'name'     => __( 'Config', 'wp-simple-firewall' ),
					'sub_navs' => \array_map(
						function () {
							return [
								'handler' => PluginAdminPages\PageDynamicLoad::class,
							];
						},
						self::con()->modules
					),
				],
				self::NAV_REPORTS        => [
					'name'     => __( 'Reports', 'wp-simple-firewall' ),
					'sub_navs' => [
						self::SUBNAV_REPORTS_LIST => [
							'handler' => PluginAdminPages\PageReports::class,
						],
					],
				],
				self::NAV_RESTRICTED     => [
					'name'     => __( 'Restricted', 'wp-simple-firewall' ),
					'sub_navs' => [
						self::SUBNAV_INDEX => [
							'handler' => PluginAdminPages\PageSecurityAdminRestricted::class,
						],
					],
				],
				self::NAV_RULES          => [
					'name'     => __( 'Rules', 'wp-simple-firewall' ),
					'sub_navs' => [
						self::SUBNAV_RULES_SUMMARY => [
							'handler' => PluginAdminPages\PageRulesSummary::class,
						],
					],
				],
				self::NAV_SCANS          => [
					'name'     => __( 'Scans', 'wp-simple-firewall' ),
					'sub_navs' => [
						self::SUBNAV_SCANS_RESULTS => [
							'handler' => PluginAdminPages\PageScansResults::class,
						],
						self::SUBNAV_SCANS_RUN     => [
							'handler' => PluginAdminPages\PageScansRun::class,
						],
					],
				],
				self::NAV_TOOLS          => [
					'name'     => __( 'Tools', 'wp-simple-firewall' ),
					'sub_navs' => [
						self::SUBNAV_TOOLS_SESSIONS => [
							'handler' => PluginAdminPages\PageUserSessions::class,
						],
						self::SUBNAV_TOOLS_DEBUG    => [
							'handler' => PluginAdminPages\PageDebug::class,
						],
						self::SUBNAV_TOOLS_DOCS     => [
							'handler' => PluginAdminPages\PageDocs::class,
						],
						self::SUBNAV_TOOLS_IMPORT   => [
							'handler' => PluginAdminPages\PageImportExport::class,
						],
					],
				],
				self::NAV_TRAFFIC        => [
					'name'     => __( 'Traffic', 'wp-simple-firewall' ),
					'sub_navs' => [
						self::SUBNAV_TRAFFIC_LOG => [
							'handler' => PluginAdminPages\PageTrafficLogTable::class,
						],
						self::SUBNAV_LOGS => [
							'handler' => PluginAdminPages\PageTrafficLogTable::class,
						],
						self::SUBNAV_LIVE        => [
							'handler' => PluginAdminPages\PageTrafficLogLive::class,
						],
					],
				],
				self::NAV_WIZARD         => [
					'name'     => __( 'Wizards', 'wp-simple-firewall' ),
					'sub_navs' => [
						self::SUBNAV_WIZARD_WELCOME => [
							'handler' => PluginAdminPages\PageMerlin::class,
						],
					],
				],
			]
		);
	}

	public static function NavExists( string $nav ) :bool {
		return isset( self::GetNavHierarchy()[ $nav ] );
	}
}