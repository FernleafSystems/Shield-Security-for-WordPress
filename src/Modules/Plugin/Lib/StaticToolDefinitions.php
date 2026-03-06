<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class StaticToolDefinitions {

	/**
	 * @return list<array{
	 *   id:string,
	 *   title:string,
	 *   icon:string,
	 *   nav:string,
	 *   subnav:string,
	 *   modes:list<string>,
	 *   search_text?:string,
	 *   search_tokens?:string,
	 *   show_in_search?:bool
	 * }>
	 */
	public static function all() :array {
		return [
			[
				'id'           => 'tool_scan_run',
				'title'        => __( 'Run Manual Scan', 'wp-simple-firewall' ),
				'icon'         => 'play-circle',
				'nav'          => PluginNavs::NAV_SCANS,
				'subnav'       => PluginNavs::SUBNAV_SCANS_RUN,
				'modes'        => [ PluginNavs::MODE_ACTIONS ],
				'search_text'  => __( 'Run A File Scan', 'wp-simple-firewall' ),
				'search_tokens'=> 'tool scan scans run file files modified hacked missing core wordpress plugins themes malware',
				'show_in_search' => true,
			],
			[
				'id'           => 'tool_ip_manager',
				'title'        => __( 'Bots & IP Rules', 'wp-simple-firewall' ),
				'icon'         => 'diagram-3',
				'nav'          => PluginNavs::NAV_IPS,
				'subnav'       => PluginNavs::SUBNAV_IPS_RULES,
				'modes'        => [ PluginNavs::MODE_INVESTIGATE ],
				'search_text'  => __( 'Manage IP Rules', 'wp-simple-firewall' ),
				'search_tokens'=> 'tool ips ip address analyse analysis rules rule manager block black white list lists bypass crowdsec table',
				'show_in_search' => true,
			],
			[
				'id'           => 'tool_activity_log',
				'title'        => __( 'WP Activity Log', 'wp-simple-firewall' ),
				'icon'         => 'person-lines-fill',
				'nav'          => PluginNavs::NAV_ACTIVITY,
				'subnav'       => PluginNavs::SUBNAV_LOGS,
				'modes'        => [ PluginNavs::MODE_INVESTIGATE ],
				'search_text'  => __( 'View User Activity Log', 'wp-simple-firewall' ),
				'search_tokens'=> 'tool audit trail activity log table traffic request requests bots review',
				'show_in_search' => true,
			],
			[
				'id'           => 'tool_traffic_log',
				'title'        => __( 'HTTP Request Log', 'wp-simple-firewall' ),
				'icon'         => 'globe',
				'nav'          => PluginNavs::NAV_TRAFFIC,
				'subnav'       => PluginNavs::SUBNAV_LOGS,
				'modes'        => [ PluginNavs::MODE_INVESTIGATE ],
				'search_text'  => __( 'View Traffic and Request Log', 'wp-simple-firewall' ),
				'search_tokens'=> 'tool activity log table traffic request requests bots review',
				'show_in_search' => true,
			],
			[
				'id'           => 'tool_sessions',
				'title'        => __( 'User Sessions', 'wp-simple-firewall' ),
				'icon'         => 'person-badge',
				'nav'          => PluginNavs::NAV_ACTIVITY,
				'subnav'       => PluginNavs::SUBNAV_ACTIVITY_SESSIONS,
				'modes'        => [ PluginNavs::MODE_INVESTIGATE ],
				'search_text'  => __( 'View User Sessions', 'wp-simple-firewall' ),
				'search_tokens'=> 'tool user users session sessions expire discard logout',
				'show_in_search' => true,
			],
			[
				'id'           => 'tool_rules_manage',
				'title'        => __( 'Custom Rules Manager', 'wp-simple-firewall' ),
				'icon'         => 'node-plus-fill',
				'nav'          => PluginNavs::NAV_RULES,
				'subnav'       => PluginNavs::SUBNAV_RULES_MANAGE,
				'modes'        => [ PluginNavs::MODE_CONFIGURE ],
			],
			[
				'id'           => 'tool_rules_build',
				'title'        => __( 'New Custom Rule', 'wp-simple-firewall' ),
				'icon'         => 'plus-circle',
				'nav'          => PluginNavs::NAV_RULES,
				'subnav'       => PluginNavs::SUBNAV_RULES_BUILD,
				'modes'        => [ PluginNavs::MODE_CONFIGURE ],
			],
			[
				'id'           => 'tool_lockdown',
				'title'        => __( 'Site Lockdown', 'wp-simple-firewall' ),
				'icon'         => 'lock-fill',
				'nav'          => PluginNavs::NAV_TOOLS,
				'subnav'       => PluginNavs::SUBNAV_TOOLS_BLOCKDOWN,
				'modes'        => [ PluginNavs::MODE_CONFIGURE ],
			],
			[
				'id'           => 'tool_importexport',
				'title'        => __( 'Import / Export', 'wp-simple-firewall' ),
				'icon'         => 'arrow-left-right',
				'nav'          => PluginNavs::NAV_TOOLS,
				'subnav'       => PluginNavs::SUBNAV_TOOLS_IMPORT,
				'modes'        => [ PluginNavs::MODE_CONFIGURE ],
				'search_text'  => __( 'Import / Export Settings', 'wp-simple-firewall' ),
				'search_tokens'=> 'tool sync import export transfer download settings configuration options slave master network',
				'show_in_search' => true,
			],
			[
				'id'           => 'tool_guidedsetup',
				'title'        => __( 'Guided Setup', 'wp-simple-firewall' ),
				'icon'         => 'compass',
				'nav'          => PluginNavs::NAV_WIZARD,
				'subnav'       => PluginNavs::SUBNAV_WIZARD_WELCOME,
				'modes'        => [ PluginNavs::MODE_CONFIGURE ],
				'search_text'  => __( 'Run Guided Setup Wizard', 'wp-simple-firewall' ),
				'search_tokens'=> 'tool setup guide guided wizard',
				'show_in_search' => true,
			],
			[
				'id'           => 'tool_debug',
				'title'        => __( 'Debug Info', 'wp-simple-firewall' ),
				'icon'         => 'bug',
				'nav'          => PluginNavs::NAV_TOOLS,
				'subnav'       => PluginNavs::SUBNAV_TOOLS_DEBUG,
				'modes'        => [ PluginNavs::MODE_CONFIGURE ],
				'search_text'  => __( 'View Debug Info', 'wp-simple-firewall' ),
				'search_tokens'=> 'tool debug info help',
				'show_in_search' => true,
			],
			[
				'id'           => 'tool_license',
				'title'        => __( 'License', 'wp-simple-firewall' ),
				'icon'         => 'award',
				'nav'          => PluginNavs::NAV_LICENSE,
				'subnav'       => PluginNavs::SUBNAV_LICENSE_CHECK,
				'modes'        => [],
				'search_tokens'=> 'tool pro license shieldpro upgrade buy purchase pricing',
				'show_in_search' => true,
			],
			[
				'id'           => 'tool_overview',
				'title'        => __( 'Overview', 'wp-simple-firewall' ),
				'icon'         => 'speedometer',
				'nav'          => PluginNavs::NAV_DASHBOARD,
				'subnav'       => PluginNavs::SUBNAV_DASHBOARD_OVERVIEW,
				'modes'        => [],
				'search_text'  => __( 'My Security Overview', 'wp-simple-firewall' ),
				'search_tokens'=> 'tool overview grade grading charts performance dashboard summary',
				'show_in_search' => true,
			],
			[
				'id'           => 'tool_reports',
				'title'        => __( 'Reports', 'wp-simple-firewall' ),
				'icon'         => 'clipboard-data-fill',
				'nav'          => PluginNavs::NAV_REPORTS,
				'subnav'       => PluginNavs::SUBNAV_REPORTS_OVERVIEW,
				'modes'        => [],
				'search_tokens'=> 'reports report reporting alert alerts',
				'show_in_search' => true,
			],
		];
	}

	/**
	 * @return list<array{
	 *   id:string,
	 *   title:string,
	 *   icon:string,
	 *   nav:string,
	 *   subnav:string,
	 *   modes:list<string>,
	 *   search_text?:string,
	 *   search_tokens?:string,
	 *   show_in_search?:bool
	 * }>
	 */
	public static function forMode( string $mode ) :array {
		return \array_values( \array_filter(
			self::all(),
			static fn( array $definition ) :bool => \in_array( $mode, $definition[ 'modes' ], true )
		) );
	}

	/**
	 * @return list<array{
	 *   id:string,
	 *   title:string,
	 *   icon:string,
	 *   nav:string,
	 *   subnav:string,
	 *   modes:list<string>,
	 *   search_text?:string,
	 *   search_tokens?:string,
	 *   show_in_search?:bool
	 * }>
	 */
	public static function forSearch() :array {
		return \array_values( \array_filter(
			self::all(),
			static fn( array $definition ) :bool => (bool)( $definition[ 'show_in_search' ] ?? false )
		) );
	}
}
