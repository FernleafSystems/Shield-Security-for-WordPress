<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Components;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Collate\RecentStats;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Obfuscate;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

class DashboardWidget {

	use ModConsumer;

	public function render( bool $forceRefresh = false ) :string {
		$con = $this->getCon();
		$modInsights = $con->getModule_Insights();
		$vars = $this->getVars( $forceRefresh );
		$vars[ 'generated_at' ] = Services::Request()
										  ->carbon()
										  ->setTimestamp( $vars[ 'generated_at' ] )
										  ->diffForHumans();
		return $this->getMod()
					->getRenderer()
					->setTemplate( '/admin/admin_dashboard_widget.twig' )
					->setRenderData( [
						'hrefs'   => [
							'overview'    => $modInsights->getUrl_SubInsightsPage( 'overview' ),
							'logo'        => $con->labels->PluginURI,
							'audit_trail' => $modInsights->getUrl_SubInsightsPage( 'audit_trail' ),
							'sessions'    => $modInsights->getUrl_SubInsightsPage( 'users' ),
							'ips'         => $modInsights->getUrl_SubInsightsPage( 'ips' ),
						],
						'flags'   => [
							'show_internal_links' => $con->isPluginAdmin()
						],
						'imgs'    => [
							'logo' => $con->labels->url_img_pagebanner,
						],
						'strings' => [
							'security_level'    => __( 'Level', 'wp-simple-firewall' ),
							'security_progress' => __( 'Overall Security Progress', 'wp-simple-firewall' ),
							'progress_overview' => __( 'Go To Overview', 'wp-simple-firewall' ),
							'recent_blocked'    => __( 'Recently Blocked', 'wp-simple-firewall' ),
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
					] )
					->render();
	}

	private function getVars( bool $refresh ) :array {
		$con = $this->getCon();
		$modInsights = $con->getModule_Insights();
		$recent = ( new RecentStats() )->setCon( $con );

		$vars = Transient::Get( $con->prefix( 'dashboard-widget-vars' ) );
		if ( $refresh || empty( $vars ) ) {
			$vars = [
				'generated_at'       => Services::Request()->ts(),
				'security_progress'  => ( new Components() )
					->setCon( $con )
					->getComponent( 'all' ),
				'jump_links'         => [
					[
						'href' => $modInsights->getUrl_SubInsightsPage( 'overview' ),
						'text' => __( 'Dashboard', 'wp-simple-firewall' ),
						'svg'  => $con->svgs->raw( 'bootstrap/speedometer.svg' ),
					],
					[
						'href' => $modInsights->getUrl_IPs(),
						'text' => __( 'IPs', 'wp-simple-firewall' ),
						'svg'  => $con->svgs->raw( 'bootstrap/diagram-3.svg' ),
					],
					[
						'href' => $modInsights->getUrl_SubInsightsPage( 'audit_trail' ),
						'text' => __( 'Activity', 'wp-simple-firewall' ),
						'svg'  => $con->svgs->raw( 'bootstrap/person-lines-fill.svg' ),
					],
					[
						'href' => $modInsights->getUrl_SubInsightsPage( 'traffic' ),
						'text' => __( 'Traffic', 'wp-simple-firewall' ),
						'svg'  => $con->svgs->raw( 'bootstrap/stoplights.svg' ),
					],
					[
						'href' => $con->getModule_Plugin()->getUrl_AdminPage(),
						'text' => __( 'Config', 'wp-simple-firewall' ),
						'svg'  => $con->svgs->raw( 'bootstrap/sliders.svg' ),
					],
				],
				'recent_events'      => array_map(
					function ( $evt ) {
						/** @var EntryVO $evt */
						return [
							'name' => $this->getCon()->loadEventsService()->getEventName( $evt->event ),
							'at'   => Services::Request()
											  ->carbon()
											  ->setTimestamp( $evt->created_at )
											  ->diffForHumans(),
						];
					},
					array_filter(
						$recent->getRecentEvents(),
						function ( $evt ) {
							return in_array( $evt->event, [
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
				'recent_ips_blocked' => array_map(
					function ( $ip ) use ( $modInsights ) {
						return [
							'ip'      => $ip->ip,
							'ip_href' => $modInsights->getUrl_IpAnalysis( $ip->ip ),
							'at'      => Services::Request()
												 ->carbon()
												 ->setTimestamp( $ip->blocked_at )
												 ->diffForHumans()
						];
					},
					$recent->getRecentlyBlockedIPs()
				),
				'recent_ips_offense' => array_map(
					function ( $ip ) use ( $modInsights ) {
						return [
							'ip'      => $ip->ip,
							'ip_href' => $modInsights->getUrl_IpAnalysis( $ip->ip ),
							'at'      => Services::Request()
												 ->carbon()
												 ->setTimestamp( $ip->last_access_at )
												 ->diffForHumans()
						];
					},
					$recent->getRecentlyOffendedIPs()
				),
				'recent_users'       => array_map(
					function ( $sess ) use ( $modInsights ) {

						$user = $sess[ 'user_login' ];
						$userHref = Services::WpUsers()->getAdminUrl_ProfileEdit( $sess[ 'user_id' ] );
						if ( $this->isObfuscateData() ) {
							$user = is_email( $user ) ?
								Obfuscate::Email( $user )
								: substr( $user, 0, 1 ).'****'.substr( $user, -1, 1 );
							$userHref = '#';
						}

						return [
							'user'      => $user,
							'user_href' => $userHref,
							'ip'        => $sess[ 'ip' ],
							'ip_href'   => $modInsights->getUrl_IpAnalysis( $sess[ 'ip' ] ),
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

	private function isObfuscateData() :bool {
		return !$this->getCon()->isPluginAdmin();
	}
}