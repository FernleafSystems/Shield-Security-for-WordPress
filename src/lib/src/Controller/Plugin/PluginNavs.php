<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin;

class PluginNavs {
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
	public const SUBNAV_REPORTS_CHARTS = 'charts';
	public const SUBNAV_REPORTS_STATS = 'stats';
	public const NAV_RULES_VIEW = 'rules';
	public const NAV_SCANS = 'scans';
	public const SUBNAV_SCANS_RESULTS = 'results';
	public const SUBNAV_SCANS_RUN = 'run';
	public const NAV_STATS = 'stats';
	public const NAV_TRAFFIC = 'traffic';
	public const SUBNAV_TRAFFIC_LOG = 'log';
	public const NAV_TOOLS = 'tools';
	public const SUBNAV_TOOLS_DEBUG = 'debug';
	public const SUBNAV_TOOLS_IMPORT = 'importexport';
	public const SUBNAV_TOOLS_DOCS = 'docs';
	public const SUBNAV_TOOLS_SESSIONS = 'sessions';
	public const NAV_WIZARD = 'merlin';
	public const SUBNAV_WIZARD_WELCOME = 'welcome';
	/** @deprecated 18.3 */
	public const NAV_OVERVIEW = 'overview';
	public const NAV_IMPORT_EXPORT = 'importexport';
	public const NAV_SCANS_RESULTS = 'scans_results';
	public const NAV_SCANS_RUN = 'scans_run';
	public const NAV_USER_SESSIONS = 'users';

	public static function GetAllNavs() :array {
		$cons = ( new \ReflectionClass( __CLASS__ ) )->getConstants();
		return \array_intersect_key( $cons, \array_flip( \array_filter(
			\array_keys( $cons ),
			function ( string $nav ) {
				return \strpos( $nav, 'NAV_' ) === 0;
			}
		) ) );
	}
}