<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AnyUserAuthRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops as EventsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	Handler,
	Meter\MeterSummary
};
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Collate\RecentStats;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Marketing\OurLatestBlogPosts;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Obfuscate;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

class WpDashboardSummary extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	use AnyUserAuthRequired;

	public const SLUG = 'render_dashboard_widget';
	public const TEMPLATE = '/admin/admin_dashboard_widget.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$vars = $this->getVars( (bool)$this->action_data[ 'refresh' ] ?? true );
		$vars[ 'generated_at' ] = Services::Request()
										  ->carbon()
										  ->setTimestamp( $vars[ 'generated_at' ] )
										  ->diffForHumans();
		return [
			'hrefs'   => [
				'overview'   => $con->plugin_urls->adminHome(),
				'logo'       => $con->labels->PluginURI,
				'activity'   => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS ),
				'sessions'   => $con->plugin_urls->adminTopNav( PluginNavs::NAV_TOOLS, PluginNavs::SUBNAV_TOOLS_SESSIONS ),
				'ips'        => $con->plugin_urls->adminIpRules(),
				'blog_posts' => 'https://clk.shldscrty.com/recentblogposts',
			],
			'flags'   => [
				'show_internal_links' => $con->isPluginAdmin()
			],
			'imgs'    => [
				'logo' => $con->labels->url_img_logo_small,
			],
			'strings' => [
				'security_level'    => __( 'Level', 'wp-simple-firewall' ),
				'security_progress' => __( 'Overall Security Progress', 'wp-simple-firewall' ),
				'progress_overview' => __( 'Go To Overview', 'wp-simple-firewall' ),
				'recent_blocked'    => __( 'Recently Blocked', 'wp-simple-firewall' ),
				'recent_blogs'      => __( 'Recent Blog Posts', 'wp-simple-firewall' ),
				'recent_offenses'   => __( 'Recent Offenses', 'wp-simple-firewall' ),
				'recent_sessions'   => __( 'Recent Sessions', 'wp-simple-firewall' ),
				'recent_activity'   => __( 'Recent Activity', 'wp-simple-firewall' ),
				'view_all'          => __( 'View All', 'wp-simple-firewall' ),
				'no_offenses'       => __( "No offenses recorded by IPs that haven't already been blocked.", 'wp-simple-firewall' ),
				'no_blocked'        => __( 'No IP blocks recorded yet.', 'wp-simple-firewall' ),
				'no_sessions'       => __( 'No new session activity recorded yet.', 'wp-simple-firewall' ),
				'no_activity'       => __( 'No site activity recorded yet.', 'wp-simple-firewall' ),
				'generated'         => __( 'Summary Generated', 'wp-simple-firewall' ),
				'refresh'           => __( 'Refresh', 'wp-simple-firewall' ),
				'events'            => __( 'Events', 'wp-simple-firewall' ),
				'time'              => __( 'Time', 'wp-simple-firewall' ),
				'user'              => __( 'User', 'wp-simple-firewall' ),
				'session_started'   => __( 'Session Started', 'wp-simple-firewall' ),
				'last_offense'      => __( 'Last Offense', 'wp-simple-firewall' ),
				'blocked'           => __( 'Blocked', 'wp-simple-firewall' ),
			],
			'vars'    => $vars,
		];
	}

	private function getVars( bool $refresh ) :array {
		$con = self::con();
		$recent = new RecentStats();

		$vars = Transient::Get( $con->prefix( 'dashboard-widget-vars' ) );
		if ( $refresh || empty( $vars ) ) {
			$vars = [
				'generated_at'       => Services::Request()->ts(),
				'security_progress'  => ( new Handler() )->getMeter( MeterSummary::class ),
				'jump_links'         => [
					[
						'href' => $con->plugin_urls->adminHome(),
						'text' => __( 'Dashboard', 'wp-simple-firewall' ),
						'svg'  => $con->svgs->raw( 'speedometer' ),
					],
					[
						'href' => $con->plugin_urls->adminIpRules(),
						'text' => __( 'IPs', 'wp-simple-firewall' ),
						'svg'  => $con->svgs->raw( 'diagram-3' ),
					],
					[
						'href' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS ),
						'text' => __( 'Activity', 'wp-simple-firewall' ),
						'svg'  => $con->svgs->raw( 'person-lines-fill' ),
					],
					[
						'href' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS ),
						'text' => __( 'Traffic', 'wp-simple-firewall' ),
						'svg'  => $con->svgs->raw( 'stoplights' ),
					],
					[
						'href' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ZONES ),
						'text' => __( 'Zones', 'wp-simple-firewall' ),
						'svg'  => $con->svgs->raw( 'gear' ),
					],
				],
				'blog_posts'         => ( new OurLatestBlogPosts() )->retrieve( 3 ),
				'recent_events'      => \array_map(
					function ( $evt ) {
						/** @var EventsDB\Record $evt */
						return [
							'name' => self::con()->comps->events->getEventName( $evt->event ),
							'at'   => Services::Request()
											  ->carbon()
											  ->setTimestamp( $evt->created_at )
											  ->diffForHumans(),
						];
					},
					\array_filter(
						$recent->getRecentEvents(),
						function ( $evt ) {
							return \in_array( $evt->event, [
								'conn_kill',
								'login_block',
								'firewall_block',
								'ip_blocked',
								'ip_offense',
								'bottrack_fakewebcrawler',
								'bottrack_logininvalid',
								'bottrack_loginfailed',
								'bottrack_xmlrpc',
								'bottrack_404',
								'spam_block_antibot',
							] );
						}
					)
				),
				'recent_ips_blocked' => \array_map(
					function ( $ip ) {
						return [
							'ip'      => $ip->ip,
							'ip_href' => self::con()->plugin_urls->ipAnalysis( $ip->ip ),
							'at'      => Services::Request()
												 ->carbon()
												 ->setTimestamp( $ip->blocked_at )
												 ->diffForHumans()
						];
					},
					$recent->getRecentlyBlockedIPs()
				),
				'recent_ips_offense' => \array_map(
					function ( $ip ) {
						return [
							'ip'      => $ip->ip,
							'ip_href' => self::con()->plugin_urls->ipAnalysis( $ip->ip ),
							'at'      => Services::Request()
												 ->carbon()
												 ->setTimestamp( $ip->last_access_at )
												 ->diffForHumans()
						];
					},
					$recent->getRecentlyOffendedIPs()
				),
				'recent_users'       => \array_map(
					function ( $sess ) {
						$user = $sess[ 'user_login' ];
						$userHref = Services::WpUsers()->getAdminUrl_ProfileEdit( $sess[ 'user_id' ] );
						if ( !self::con()->isPluginAdmin() ) {
							$user = is_email( $user ) ?
								Obfuscate::Email( $user ) : \substr( $user, 0, 1 ).'****'.\substr( $user, -1, 1 );
							$userHref = '#';
						}

						return [
							'user'      => $user,
							'user_href' => $userHref,
							'ip'        => $sess[ 'ip' ],
							'ip_href'   => self::con()->plugin_urls->ipAnalysis( $sess[ 'ip' ] ),
							'at'        => Services::Request()
												   ->carbon()
												   ->setTimestamp( (int)$sess[ 'last_login_at' ] )
												   ->diffForHumans()
						];
					},
					$recent->getRecentUserSessions()
				),
			];
			Transient::Set( $con->prefix( 'dashboard-widget-vars' ), $vars, 30 );
		}

		return $vars;
	}
}