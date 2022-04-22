<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Collate\RecentStats;
use FernleafSystems\Wordpress\Services\Services;

class DashboardWidget {

	use ModConsumer;

	public function render() :string {
		$con = $this->getCon();
		$modInsights = $con->getModule_Insights();
		$labels = $con->getLabels();

		$recent = ( new RecentStats() )->setCon( $this->getCon() );

		$recentEvents = array_map(
			function ( $evt ) {
				/** @var EntryVO $evt */
				return [
					'name' => $this->getCon()->service_events->getEventName( $evt->event ),
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
		);

		return $this->getMod()
					->getRenderer()
					->setTemplate( '/admin/admin_dashboard_widget.twig' )
					->setRenderData( [
						'hrefs' => [
							'logo' => $labels[ 'PluginURI' ]
						],
						'flags' => [
							'show_internal_links' => $con->isPluginAdmin()
						],
						'imgs'  => [
							'logo' => $con->urls->forImage( 'pluginlogo_banner-772x250.png' )
						],
						'vars'  => [
							'jump_links'         => [
								[
									'href' => $modInsights->getUrl_SubInsightsPage( 'overview' ),
									'text' => __( 'Overview', 'wp-simple-firewall' ),
								],
								[
									'href' => $modInsights->getUrl_IPs(),
									'text' => __( 'IPs', 'wp-simple-firewall' ),
								],
								[
									'href' => $modInsights->getUrl_SubInsightsPage( 'audit_trail' ),
									'text' => __( 'Activity', 'wp-simple-firewall' ),
								],
								[
									'href' => $modInsights->getUrl_SubInsightsPage( 'traffic' ),
									'text' => __( 'Traffic', 'wp-simple-firewall' ),
								],
								[
									'href' => $con->getModule_Plugin()->getUrl_AdminPage(),
									'text' => __( 'Config', 'wp-simple-firewall' ),
								],
							],
							'recent_events'      => $recentEvents,
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
									return [
										'user'      => $sess[ 'user_login' ],
										'user_href' => Services::WpUsers()
															   ->getAdminUrl_ProfileEdit( $sess[ 'user_id' ] ),
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
						],
					] )
					->render();
	}
}