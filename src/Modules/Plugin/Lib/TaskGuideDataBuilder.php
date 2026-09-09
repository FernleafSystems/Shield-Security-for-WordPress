<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @phpstan-type TaskGuideChoice array{
 *   key:string,
 *   label:string,
 *   icon_class:string,
 *   target:array{type:'node',node_key:string}|array{type:'href',href:string}
 * }
 * @phpstan-type TaskGuideNode array{
 *   key:string,
 *   title:string,
 *   choices:list<TaskGuideChoice>
 * }
 * @phpstan-type TaskGuideLauncher array{
 *   label:string,
 *   description:string,
 *   cta:string,
 *   accessible_label:string
 * }
 * @phpstan-type TaskGuideGraph array{
 *   initial_node_key:string,
 *   strings:array{back_label:string,close_label:string},
 *   nodes:list<TaskGuideNode>
 * }
 */
class TaskGuideDataBuilder {

	use PluginControllerConsumer;

	/**
	 * @return TaskGuideLauncher
	 */
	public function buildLauncher() :array {
		return [
			'label'            => __( 'Let Shield guide you', 'wp-simple-firewall' ),
			'description'      => __( 'Jump quickly to where you need to go.', 'wp-simple-firewall' ),
			'cta'              => __( 'Find a task', 'wp-simple-firewall' ),
			'accessible_label' => __( 'Let Shield guide you. Jump quickly to where you need to go. Find a task.', 'wp-simple-firewall' ),
		];
	}

	/**
	 * @return TaskGuideGraph
	 */
	public function buildGraph() :array {
		return [
			'initial_node_key' => 'start',
			'strings'          => [
				'back_label'  => __( 'Back', 'wp-simple-firewall' ),
				'close_label' => __( 'Close', 'wp-simple-firewall' ),
			],
			'nodes'            => [
				$this->buildNode(
					'start',
					__( 'What do you want to do?', 'wp-simple-firewall' ),
					[
						$this->buildNodeChoice( 'manage_ip_access', __( 'Manage IP access', 'wp-simple-firewall' ), 'bi bi-shield-lock', 'ip_access' ),
						$this->buildNodeChoice( 'run_or_review_scans', __( 'Run or review scans', 'wp-simple-firewall' ), 'bi bi-clipboard2-pulse', 'scans' ),
						$this->buildNodeChoice( 'investigate', __( 'Investigate an issue', 'wp-simple-firewall' ), 'bi bi-search', 'investigate' ),
						$this->buildNodeChoice( 'configure', __( 'Configure the plugin', 'wp-simple-firewall' ), 'bi bi-sliders', 'configure' ),
						$this->buildNodeChoice( 'view_reports', __( 'View reports', 'wp-simple-firewall' ), 'bi bi-bar-chart-line', 'reports' ),
					]
				),
				$this->buildNode(
					'ip_access',
					__( 'I want to manage IP access...', 'wp-simple-firewall' ),
					[
						$this->buildHrefChoice( 'block_or_allow_ip', __( 'Block or allow an IP address', 'wp-simple-firewall' ), 'bi bi-shield-lock', $this->buildHref( PluginNavs::NAV_IPS, PluginNavs::SUBNAV_IPS_RULES ) ),
						$this->buildHrefChoice( 'investigate_ip_activity', __( 'Investigate an IP address', 'wp-simple-firewall' ), 'bi bi-globe', $this->buildHref( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_IP ) ),
					]
				),
				$this->buildNode(
					'scans',
					__( 'I want to run or review scans...', 'wp-simple-firewall' ),
					[
						$this->buildHrefChoice( 'view_scan_results', __( 'View scan results', 'wp-simple-firewall' ), 'bi bi-list-check', $this->buildHref( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_OVERVIEW, [ 'zone' => 'scans' ] ) ),
						$this->buildHrefChoice( 'run_scan', __( 'Run a scan', 'wp-simple-firewall' ), 'bi bi-play-circle', $this->buildHref( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RUN ) ),
					]
				),
				$this->buildNode(
					'investigate',
					__( 'I want to investigate...', 'wp-simple-firewall' ),
					[
						$this->buildHrefChoice( 'investigate_user_activity', __( 'User activity', 'wp-simple-firewall' ), 'bi bi-person', $this->buildHref( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_USER ) ),
						$this->buildHrefChoice( 'investigate_ip_activity', __( 'An IP address', 'wp-simple-firewall' ), 'bi bi-globe', $this->buildHref( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_IP ) ),
						$this->buildHrefChoice( 'investigate_plugin_activity', __( 'A plugin', 'wp-simple-firewall' ), 'bi bi-plugin', $this->buildHref( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN ) ),
						$this->buildHrefChoice( 'investigate_theme_activity', __( 'A theme', 'wp-simple-firewall' ), 'bi bi-palette', $this->buildHref( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_THEME ) ),
						$this->buildHrefChoice( 'investigate_core_activity', __( 'WordPress core', 'wp-simple-firewall' ), 'bi bi-wordpress', $this->buildHref( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_CORE ) ),
					]
				),
				$this->buildNode(
					'configure',
					__( 'I want to configure...', 'wp-simple-firewall' ),
					[
						$this->buildHrefChoice( 'configure_firewall', __( 'The firewall', 'wp-simple-firewall' ), 'bi bi-shield-check', $this->buildHref( PluginNavs::NAV_ZONES, PluginNavs::SUBNAV_ZONES_OVERVIEW, [ 'zone' => 'firewall' ] ) ),
						$this->buildHrefChoice( 'configure_ips', __( 'Bots and IP access', 'wp-simple-firewall' ), 'bi bi-shield-lock', $this->buildHref( PluginNavs::NAV_ZONES, PluginNavs::SUBNAV_ZONES_OVERVIEW, [ 'zone' => 'ips' ] ) ),
						$this->buildHrefChoice( 'configure_scans', __( 'Scans', 'wp-simple-firewall' ), 'bi bi-clipboard2-pulse', $this->buildHref( PluginNavs::NAV_ZONES, PluginNavs::SUBNAV_ZONES_OVERVIEW, [ 'zone' => 'scans' ] ) ),
						$this->buildHrefChoice( 'configure_login', __( 'Login protection', 'wp-simple-firewall' ), 'bi bi-key', $this->buildHref( PluginNavs::NAV_ZONES, PluginNavs::SUBNAV_ZONES_OVERVIEW, [ 'zone' => 'login' ] ) ),
						$this->buildHrefChoice( 'configure_users', __( 'User account protection', 'wp-simple-firewall' ), 'bi bi-people', $this->buildHref( PluginNavs::NAV_ZONES, PluginNavs::SUBNAV_ZONES_OVERVIEW, [ 'zone' => 'users' ] ) ),
						$this->buildHrefChoice( 'configure_other', __( 'Another setting', 'wp-simple-firewall' ), 'bi bi-sliders', $this->buildHref( PluginNavs::NAV_ZONES, PluginNavs::SUBNAV_ZONES_OVERVIEW ) ),
					]
				),
				$this->buildNode(
					'reports',
					__( 'I want to view reports...', 'wp-simple-firewall' ),
					[
						$this->buildHrefChoice( 'security_reports', __( 'Security reports', 'wp-simple-firewall' ), 'bi bi-file-earmark-text', $this->buildHref( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_LIST ) ),
						$this->buildHrefChoice( 'charts_and_trends', __( 'Charts and trends', 'wp-simple-firewall' ), 'bi bi-graph-up-arrow', $this->buildHref( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_CHARTS ) ),
						$this->buildHrefChoice( 'reporting_settings', __( 'Report and alert settings', 'wp-simple-firewall' ), 'bi bi-bell', $this->buildHref( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_SETTINGS ) ),
					]
				),
			],
		];
	}

	/**
	 * @param list<TaskGuideChoice> $choices
	 * @return TaskGuideNode
	 */
	private function buildNode( string $key, string $title, array $choices ) :array {
		return [
			'key'     => $key,
			'title'   => $title,
			'choices' => $choices,
		];
	}

	/**
	 * @return TaskGuideChoice
	 */
	private function buildNodeChoice( string $key, string $label, string $iconClass, string $nodeKey ) :array {
		return [
			'key'        => $key,
			'label'      => $label,
			'icon_class' => $iconClass,
			'target'      => [
				'type'     => 'node',
				'node_key' => $nodeKey,
			],
		];
	}

	/**
	 * @return TaskGuideChoice
	 */
	private function buildHrefChoice( string $key, string $label, string $iconClass, string $href ) :array {
		return [
			'key'        => $key,
			'label'      => $label,
			'icon_class' => $iconClass,
			'target'      => [
				'type' => 'href',
				'href' => $href,
			],
		];
	}

	private function buildHref( string $nav, string $subNav, array $query = [] ) :string {
		$href = $nav === PluginNavs::NAV_REPORTS
			? self::con()->plugin_urls->reportsHome( $subNav )
			: self::con()->plugin_urls->adminTopNav( $nav, $subNav );
		if ( !empty( $query ) ) {
			$href .= ( \strpos( $href, '?' ) === false ? '?' : '&' ).\http_build_query( $query, '', '&', \PHP_QUERY_RFC3986 );
		}
		return $href;
	}
}
